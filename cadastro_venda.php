<?php require_once __DIR__ . '/auth.php'; exigirLogin(); require_once __DIR__ . '/config/database.php';

$pdo = Database::getConnection();

$erros = [];
$sucesso = '';

$clienteId = $_POST['cliente_id'] ?? '';
$formaPagamento = $_POST['forma_pagamento'] ?? '';
$itensLote = $_POST['lote_id'] ?? [];
$itensQtd = $_POST['quantidade'] ?? [];
$itensPreco = $_POST['preco_unitario'] ?? [];

$clientes = $pdo->query('SELECT id, nome FROM clientes ORDER BY nome')->fetchAll();

$lotesDisponiveis = $pdo->query(
    "SELECT l.id, l.numero_lote, l.quantidade_disponivel, l.data_validade,
            p.id AS produto_id, p.nome AS produto_nome, p.preco_venda
     FROM lotes l
     JOIN produtos p ON p.id = l.produto_id
     WHERE l.quantidade_disponivel > 0 AND p.ativo = 1
     ORDER BY (l.data_validade IS NULL), l.data_validade, p.nome"
)->fetchAll();

$mapaLotes = [];
foreach ($lotesDisponiveis as $l) {
    $mapaLotes[$l['id']] = $l;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($clienteId !== '' && !ctype_digit((string)$clienteId)) {
        $erros[] = 'Cliente inválido.';
    }

    if (!in_array($formaPagamento, ['dinheiro', 'pix', 'debito', 'credito', 'boleto'], true)) {
        $erros[] = 'Selecione a forma de pagamento.';
    }

    $itensValidos = [];
    $totalPorLote = [];

    for ($i = 0; $i < count($itensLote); $i++) {
        $loteId = $itensLote[$i] ?? '';
        $qtd = $itensQtd[$i] ?? '';
        $preco = $itensPreco[$i] ?? '';

        if ($loteId === '' && $qtd === '' && $preco === '') {
            continue; // linha em branco, ignora
        }

        if (!ctype_digit((string)$loteId) || !isset($mapaLotes[(int)$loteId])) {
            $erros[] = 'Item ' . ($i + 1) . ': selecione um lote válido.';
            continue;
        }

        if (!ctype_digit((string)$qtd) || (int)$qtd <= 0) {
            $erros[] = 'Item ' . ($i + 1) . ': quantidade inválida.';
            continue;
        }

        if (!is_numeric($preco) || (float)$preco < 0) {
            $erros[] = 'Item ' . ($i + 1) . ': preço unitário inválido.';
            continue;
        }

        $loteId = (int)$loteId;
        $qtd = (int)$qtd;

        $totalPorLote[$loteId] = ($totalPorLote[$loteId] ?? 0) + $qtd;

        if ($totalPorLote[$loteId] > (int)$mapaLotes[$loteId]['quantidade_disponivel']) {
            $erros[] = 'Item ' . ($i + 1) . ': quantidade total pedida para o lote "' . $mapaLotes[$loteId]['numero_lote']
                . '" excede o disponível (' . (int)$mapaLotes[$loteId]['quantidade_disponivel'] . ' unidades).';
            continue;
        }

        $itensValidos[] = [
            'lote_id' => $loteId,
            'produto_id' => (int)$mapaLotes[$loteId]['produto_id'],
            'quantidade' => $qtd,
            'preco_unitario' => (float)$preco,
        ];
    }

    if (empty($itensValidos) && empty($erros)) {
        $erros[] = 'Adicione pelo menos um item à venda.';
    }

    if (empty($erros)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "INSERT INTO vendas (cliente_id, usuario_id, forma_pagamento, valor_total, status)
                 VALUES (:cliente_id, :usuario_id, :forma_pagamento, 0, 'concluida')"
            );
            $stmt->execute([
                'cliente_id' => $clienteId !== '' ? $clienteId : null,
                'usuario_id' => $_SESSION['usuario_id'],
                'forma_pagamento' => $formaPagamento,
            ]);
            $vendaId = $pdo->lastInsertId();

            $valorTotal = 0.0;

            foreach ($itensValidos as $item) {
                $stmt = $pdo->prepare('SELECT * FROM lotes WHERE id = :id FOR UPDATE');
                $stmt->execute(['id' => $item['lote_id']]);
                $lote = $stmt->fetch();

                if (!$lote || (int)$lote['quantidade_disponivel'] < $item['quantidade']) {
                    throw new RuntimeException('Estoque insuficiente para o lote selecionado.');
                }

                $subtotal = round($item['quantidade'] * $item['preco_unitario'], 2);
                $valorTotal += $subtotal;

                $stmt = $pdo->prepare(
                    'INSERT INTO venda_itens (venda_id, produto_id, lote_id, quantidade, preco_unitario, subtotal)
                     VALUES (:venda_id, :produto_id, :lote_id, :quantidade, :preco_unitario, :subtotal)'
                );
                $stmt->execute([
                    'venda_id' => $vendaId,
                    'produto_id' => $item['produto_id'],
                    'lote_id' => $item['lote_id'],
                    'quantidade' => $item['quantidade'],
                    'preco_unitario' => $item['preco_unitario'],
                    'subtotal' => $subtotal,
                ]);

                $stmt = $pdo->prepare(
                    'UPDATE lotes SET quantidade_disponivel = quantidade_disponivel - :quantidade WHERE id = :id'
                );
                $stmt->execute(['quantidade' => $item['quantidade'], 'id' => $item['lote_id']]);

                $stmt = $pdo->prepare(
                    "INSERT INTO estoque_movimentacoes (produto_id, lote_id, tipo, quantidade, motivo, usuario_id)
                     VALUES (:produto_id, :lote_id, 'saida', :quantidade, :motivo, :usuario_id)"
                );
                $stmt->execute([
                    'produto_id' => $item['produto_id'],
                    'lote_id' => $item['lote_id'],
                    'quantidade' => $item['quantidade'],
                    'motivo' => 'Venda #' . $vendaId,
                    'usuario_id' => $_SESSION['usuario_id'],
                ]);
            }

            $stmt = $pdo->prepare('UPDATE vendas SET valor_total = :total WHERE id = :id');
            $stmt->execute(['total' => $valorTotal, 'id' => $vendaId]);

            $pdo->commit();

            $sucesso = 'Venda #' . $vendaId . ' registrada! Total: R$ ' . number_format($valorTotal, 2, ',', '.');

            // Reseta o formulário e recarrega os lotes com saldo atualizado
            $clienteId = '';
            $formaPagamento = '';
            $itensLote = [];
            $itensQtd = [];
            $itensPreco = [];

            $lotesDisponiveis = $pdo->query(
                "SELECT l.id, l.numero_lote, l.quantidade_disponivel, l.data_validade,
                        p.id AS produto_id, p.nome AS produto_nome, p.preco_venda
                 FROM lotes l
                 JOIN produtos p ON p.id = l.produto_id
                 WHERE l.quantidade_disponivel > 0 AND p.ativo = 1
                 ORDER BY (l.data_validade IS NULL), l.data_validade, p.nome"
            )->fetchAll();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erros[] = 'Não foi possível registrar a venda: estoque pode ter mudado, confira as quantidades e tente novamente.';
        }
    }
}

$ultimasVendas = $pdo->query(
    "SELECT v.id, v.data_venda, v.valor_total, v.forma_pagamento, v.status, c.nome AS cliente_nome,
            (SELECT COUNT(*) FROM venda_itens vi WHERE vi.venda_id = v.id) AS total_itens
     FROM vendas v
     LEFT JOIN clientes c ON c.id = v.cliente_id
     ORDER BY v.data_venda DESC
     LIMIT 10"
)->fetchAll();

$dadosLotesJs = array_map(function ($l) {
    return [
        'id' => (int)$l['id'],
        'label' => $l['produto_nome'] . ' — Lote ' . $l['numero_lote']
            . ' (disp.: ' . (int)$l['quantidade_disponivel'] . ')',
        'disponivel' => (int)$l['quantidade_disponivel'],
        'preco' => (float)$l['preco_venda'],
    ];
}, $lotesDisponiveis);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registrar Venda — WSI</title>
<link rel="stylesheet" href="assets/css/app.css">
<style>
    .itens-table input, .itens-table select { padding: 8px 10px; font-size: 13px; }
    .itens-table td { vertical-align: middle; }
</style>
</head>
<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>

<main class="wide">
    <h1>Registrar venda</h1>
    <p class="sub">Cria a venda, os itens e já dá baixa automática nos lotes escolhidos.</p>
    <p class="sub" style="margin-top:-22px;">Para saída sem venda (perda, ajuste), use <a href="cadastro_saida.php" style="color: var(--accent);">Cadastro de saídas</a>.</p>

    <div class="card" style="margin-bottom: 24px;">
        <div class="card__header">
            <p class="card__eyebrow">Vendas · Nova venda</p>
            <h2 class="card__title">Dados da venda</h2>
        </div>

        <form method="POST" action="" class="stack" id="formVenda" novalidate>
            <?php if (!empty($erros)): ?>
                <div class="msg msg--error">
                    <strong>Corrija os itens abaixo:</strong>
                    <ul>
                        <?php foreach ($erros as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($sucesso): ?>
                <div class="msg msg--success"><?= htmlspecialchars($sucesso) ?></div>
            <?php endif; ?>

            <?php if (empty($lotesDisponiveis)): ?>
                <div class="msg msg--error">Nenhum lote com saldo disponível no momento.</div>
            <?php endif; ?>

            <div class="field-grid">
                <div>
                    <label for="cliente_id">Cliente</label>
                    <select id="cliente_id" name="cliente_id">
                        <option value="">Consumidor não identificado</option>
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= (string)$clienteId === (string)$c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="forma_pagamento">Forma de pagamento</label>
                    <select id="forma_pagamento" name="forma_pagamento" required>
                        <option value="">Selecione...</option>
                        <?php foreach (['dinheiro' => 'Dinheiro', 'pix' => 'Pix', 'debito' => 'Débito', 'credito' => 'Crédito', 'boleto' => 'Boleto'] as $valor => $rotulo): ?>
                            <option value="<?= $valor ?>" <?= $formaPagamento === $valor ? 'selected' : '' ?>><?= $rotulo ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="table-wrap">
                <table class="data-table itens-table" id="tabelaItens">
                    <thead>
                        <tr>
                            <th style="width:45%;">Produto / Lote</th>
                            <th>Quantidade</th>
                            <th>Preço unit. (R$)</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="corpoItens"></tbody>
                </table>
            </div>

            <div>
                <button type="button" class="btn btn--ghost btn--sm" id="btnAdicionarItem" <?= empty($lotesDisponiveis) ? 'disabled' : '' ?>>+ Adicionar item</button>
            </div>

            <button type="submit" <?= empty($lotesDisponiveis) ? 'disabled' : '' ?>>Registrar venda</button>
        </form>
    </div>

    <div class="card">
        <div class="card__header">
            <p class="card__eyebrow">Conferência</p>
            <h2 class="card__title">Últimas vendas registradas</h2>
        </div>
        <div class="table-wrap">
            <?php if (empty($ultimasVendas)): ?>
                <p class="empty-state">Nenhuma venda registrada ainda.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cliente</th>
                            <th>Itens</th>
                            <th>Total</th>
                            <th>Pagamento</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimasVendas as $v): ?>
                            <tr>
                                <td>#<?= (int)$v['id'] ?></td>
                                <td><?= htmlspecialchars($v['cliente_nome'] ?: 'Consumidor não identificado') ?></td>
                                <td><?= (int)$v['total_itens'] ?></td>
                                <td>R$ <?= number_format((float)$v['valor_total'], 2, ',', '.') ?></td>
                                <td><?= htmlspecialchars(strtoupper($v['forma_pagamento'])) ?></td>
                                <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($v['data_venda']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <a href="home.php" class="back">← Voltar para o início</a>
</main>

<script>
    const lotesDisponiveis = <?= json_encode($dadosLotesJs) ?>;
    const corpoItens = document.getElementById('corpoItens');

    function opcoesLotes() {
        let html = '<option value="">Selecione...</option>';
        lotesDisponiveis.forEach(function (l) {
            html += `<option value="${l.id}" data-preco="${l.preco}" data-disponivel="${l.disponivel}">${l.label}</option>`;
        });
        return html;
    }

    function adicionarLinha() {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <select name="lote_id[]" class="select-lote" required>${opcoesLotes()}</select>
            </td>
            <td><input type="number" name="quantidade[]" min="1" step="1" required></td>
            <td><input type="number" name="preco_unitario[]" min="0" step="0.01" required></td>
            <td><button type="button" class="btn btn--ghost btn--sm btn-remover">Remover</button></td>
        `;
        corpoItens.appendChild(tr);

        const select = tr.querySelector('.select-lote');
        const inputPreco = tr.querySelector('input[name="preco_unitario[]"]');

        select.addEventListener('change', function () {
            const opcao = select.options[select.selectedIndex];
            const preco = opcao.getAttribute('data-preco');
            if (preco) {
                inputPreco.value = parseFloat(preco).toFixed(2);
            }
        });

        tr.querySelector('.btn-remover').addEventListener('click', function () {
            tr.remove();
        });
    }

    document.getElementById('btnAdicionarItem').addEventListener('click', adicionarLinha);

    if (lotesDisponiveis.length > 0) {
        adicionarLinha();
    }
</script>

</body>
</html>
