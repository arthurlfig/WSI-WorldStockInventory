<?php require_once __DIR__ . '/auth.php'; exigirLogin(); require_once __DIR__ . '/config/database.php';

/*
 * EE-31 — Alterar venda.
 * Altera cliente, forma de pagamento e status (pendente/concluída).
 * Itens e valores não mudam aqui; para desfazer a venda use "Cancelar venda".
 */

$pdo = Database::getConnection();

$erros = [];
$sucesso = '';

$vendaId = $_GET['id'] ?? $_POST['id'] ?? '';
if ($vendaId === '' || !ctype_digit((string)$vendaId)) {
    header('Location: historico.php?aba=vendas');
    exit;
}

function carregarVenda(PDO $pdo, string $id)
{
    $stmt = $pdo->prepare(
        'SELECT v.*, c.nome AS cliente_nome
         FROM vendas v
         LEFT JOIN clientes c ON c.id = v.cliente_id
         WHERE v.id = :id'
    );
    $stmt->execute(['id' => $id]);
    return $stmt->fetch();
}

$venda = carregarVenda($pdo, (string)$vendaId);
if (!$venda) {
    header('Location: historico.php?aba=vendas');
    exit;
}

$cancelada = $venda['status'] === 'cancelada';

$clientes = $pdo->query('SELECT id, nome FROM clientes ORDER BY nome')->fetchAll();

$stmt = $pdo->prepare(
    'SELECT vi.quantidade, vi.preco_unitario, vi.subtotal, p.nome AS produto_nome, l.numero_lote
     FROM venda_itens vi
     JOIN produtos p ON p.id = vi.produto_id
     LEFT JOIN lotes l ON l.id = vi.lote_id
     WHERE vi.venda_id = :id'
);
$stmt->execute(['id' => $venda['id']]);
$itens = $stmt->fetchAll();

$valores = [
    'cliente_id'      => $venda['cliente_id'] !== null ? (string)$venda['cliente_id'] : '',
    'forma_pagamento' => (string)$venda['forma_pagamento'],
    'status'          => (string)$venda['status'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$cancelada) {

    foreach ($valores as $campo => $valor) {
        if (isset($_POST[$campo])) {
            $valores[$campo] = trim($_POST[$campo]);
        }
    }

    if ($valores['cliente_id'] !== '') {
        if (!ctype_digit($valores['cliente_id'])) {
            $erros[] = 'Cliente inválido.';
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM clientes WHERE id = :id');
            $stmt->execute(['id' => $valores['cliente_id']]);
            if ((int)$stmt->fetchColumn() === 0) {
                $erros[] = 'Cliente não encontrado.';
            }
        }
    }

    if (!in_array($valores['forma_pagamento'], ['dinheiro', 'pix', 'debito', 'credito', 'boleto'], true)) {
        $erros[] = 'Selecione a forma de pagamento.';
    }

    if (!in_array($valores['status'], ['pendente', 'concluida'], true)) {
        $erros[] = 'Status inválido. Para cancelar a venda, use "Cancelar venda".';
    }

    if (empty($erros)) {
        try {
            // "AND status <> 'cancelada'" evita reabrir uma venda cancelada em paralelo
            $stmt = $pdo->prepare(
                "UPDATE vendas
                 SET cliente_id = :cliente_id, forma_pagamento = :forma_pagamento, status = :status
                 WHERE id = :id AND status <> 'cancelada'"
            );
            $stmt->execute([
                'cliente_id'      => $valores['cliente_id'] !== '' ? $valores['cliente_id'] : null,
                'forma_pagamento' => $valores['forma_pagamento'],
                'status'          => $valores['status'],
                'id'              => $venda['id'],
            ]);

            $sucesso = 'Venda #' . (int)$venda['id'] . ' atualizada com sucesso!';
            $venda = carregarVenda($pdo, (string)$venda['id']);
            $cancelada = $venda['status'] === 'cancelada';
        } catch (PDOException $e) {
            $erros[] = 'Não foi possível atualizar a venda.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Alterar Venda — WSI</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>

<main class="wide">
    <h1>Alterar venda</h1>
    <p class="sub">Altere cliente, forma de pagamento ou status. Itens e valores não podem ser editados.</p>

    <div class="card" style="margin-bottom: 24px;">
        <div class="card__header">
            <p class="card__eyebrow">Vendas · Venda #<?= (int)$venda['id'] ?></p>
            <h2 class="card__title">R$ <?= number_format((float)$venda['valor_total'], 2, ',', '.') ?></h2>
            <p class="card__subtitle">
                <?= htmlspecialchars(date('d/m/Y H:i', strtotime($venda['data_venda']))) ?> ·
                <?= count($itens) ?> item(ns)
            </p>
        </div>

        <?php if ($cancelada): ?>
            <div class="msg msg--error">Esta venda está cancelada e não pode ser alterada.</div>
        <?php endif; ?>

        <div class="table-wrap" style="margin-bottom: 18px;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Lote</th>
                        <th>Qtde.</th>
                        <th>Preço unit.</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itens as $i): ?>
                        <tr>
                            <td><?= htmlspecialchars($i['produto_nome']) ?></td>
                            <td><?= htmlspecialchars($i['numero_lote'] ?: '—') ?></td>
                            <td><?= (int)$i['quantidade'] ?></td>
                            <td>R$ <?= number_format((float)$i['preco_unitario'], 2, ',', '.') ?></td>
                            <td>R$ <?= number_format((float)$i['subtotal'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (!$cancelada): ?>
            <form method="POST" action="" class="stack" novalidate>
                <input type="hidden" name="id" value="<?= (int)$venda['id'] ?>">

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

                <div class="field-grid">
                    <div>
                        <label for="cliente_id">Cliente</label>
                        <select id="cliente_id" name="cliente_id">
                            <option value="">Consumidor não identificado</option>
                            <?php foreach ($clientes as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= (string)$valores['cliente_id'] === (string)$c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="forma_pagamento">Forma de pagamento</label>
                        <select id="forma_pagamento" name="forma_pagamento">
                            <?php foreach (['dinheiro' => 'Dinheiro', 'pix' => 'PIX', 'debito' => 'Débito', 'credito' => 'Crédito', 'boleto' => 'Boleto'] as $valor => $rotulo): ?>
                                <option value="<?= $valor ?>" <?= $valores['forma_pagamento'] === $valor ? 'selected' : '' ?>><?= $rotulo ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="concluida" <?= $valores['status'] === 'concluida' ? 'selected' : '' ?>>Concluída</option>
                            <option value="pendente" <?= $valores['status'] === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                        </select>
                    </div>
                </div>

                <div style="display:flex; gap:10px;">
                    <button type="submit">Salvar alterações</button>
                    <a href="historico.php?aba=vendas" class="btn btn--ghost">Voltar</a>
                </div>
            </form>
        <?php else: ?>
            <a href="historico.php?aba=vendas" class="btn btn--ghost">Voltar ao histórico</a>
        <?php endif; ?>
    </div>

    <a href="historico.php?aba=vendas" class="back">← Voltar para o histórico de vendas</a>
</main>

</body>
</html>
