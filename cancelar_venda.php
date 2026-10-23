<?php require_once __DIR__ . '/auth.php'; exigirLogin(); require_once __DIR__ . '/config/database.php';

/*
 * EE-32 — Cancelar venda.
 * Marca a venda como cancelada, devolve as quantidades aos lotes e registra uma
 * movimentação de entrada para cada item. A venda não é apagada (histórico preservado).
 */

$pdo = Database::getConnection();

$erros = [];
$concluido = false;
$mensagemFinal = '';

$vendaId = $_GET['id'] ?? $_POST['id'] ?? '';
if ($vendaId === '' || !ctype_digit((string)$vendaId)) {
    header('Location: historico.php?aba=vendas');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT v.*, c.nome AS cliente_nome
     FROM vendas v
     LEFT JOIN clientes c ON c.id = v.cliente_id
     WHERE v.id = :id'
);
$stmt->execute(['id' => $vendaId]);
$venda = $stmt->fetch();

if (!$venda) {
    header('Location: historico.php?aba=vendas');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT vi.quantidade, vi.preco_unitario, vi.subtotal, p.nome AS produto_nome, l.numero_lote
     FROM venda_itens vi
     JOIN produtos p ON p.id = vi.produto_id
     LEFT JOIN lotes l ON l.id = vi.lote_id
     WHERE vi.venda_id = :id'
);
$stmt->execute(['id' => $venda['id']]);
$itens = $stmt->fetchAll();

$jaCancelada = $venda['status'] === 'cancelada';
$motivo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$jaCancelada) {

    $motivo = trim($_POST['motivo'] ?? '');

    if ($motivo === '') {
        $erros[] = 'Informe o motivo do cancelamento.';
    }

    if (empty($erros)) {
        try {
            $pdo->beginTransaction();

            // Trava a venda para impedir dois cancelamentos simultâneos (devolveria o estoque duas vezes)
            $stmt = $pdo->prepare('SELECT * FROM vendas WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $venda['id']]);
            $vendaAtual = $stmt->fetch();

            if (!$vendaAtual) {
                $erros[] = 'Venda não encontrada.';
            } elseif ($vendaAtual['status'] === 'cancelada') {
                $erros[] = 'Esta venda já foi cancelada.';
            }

            if (empty($erros)) {
                $stmt = $pdo->prepare(
                    'SELECT produto_id, lote_id, quantidade FROM venda_itens WHERE venda_id = :id'
                );
                $stmt->execute(['id' => $vendaAtual['id']]);
                $itensVenda = $stmt->fetchAll();

                $motivoMov = mb_substr('Cancelamento da venda #' . (int)$vendaAtual['id'] . ': ' . $motivo, 0, 255);

                foreach ($itensVenda as $item) {
                    // Se o lote não existe mais (item sem lote), não há saldo a devolver
                    if ($item['lote_id'] === null) {
                        continue;
                    }

                    $stmt = $pdo->prepare('SELECT id FROM lotes WHERE id = :id FOR UPDATE');
                    $stmt->execute(['id' => $item['lote_id']]);
                    if (!$stmt->fetch()) {
                        continue;
                    }

                    $stmt = $pdo->prepare(
                        'UPDATE lotes SET quantidade_disponivel = quantidade_disponivel + :quantidade WHERE id = :id'
                    );
                    $stmt->execute(['quantidade' => $item['quantidade'], 'id' => $item['lote_id']]);

                    $stmt = $pdo->prepare(
                        "INSERT INTO estoque_movimentacoes
                            (produto_id, lote_id, tipo, quantidade, motivo, usuario_id)
                         VALUES
                            (:produto_id, :lote_id, 'entrada', :quantidade, :motivo, :usuario_id)"
                    );
                    $stmt->execute([
                        'produto_id' => $item['produto_id'],
                        'lote_id'    => $item['lote_id'],
                        'quantidade' => $item['quantidade'],
                        'motivo'     => $motivoMov,
                        'usuario_id' => $_SESSION['usuario_id'],
                    ]);
                }

                $stmt = $pdo->prepare("UPDATE vendas SET status = 'cancelada' WHERE id = :id");
                $stmt->execute(['id' => $vendaAtual['id']]);

                $pdo->commit();

                $concluido = true;
                $mensagemFinal = 'Venda #' . (int)$vendaAtual['id'] . ' cancelada. O estoque dos itens foi devolvido aos lotes.';
            } else {
                $pdo->rollBack();
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erros[] = 'Não foi possível cancelar a venda.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cancelar Venda — WSI</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>

<main class="wide">
    <h1>Cancelar venda</h1>
    <p class="sub">O cancelamento devolve os itens ao estoque e mantém a venda no histórico como cancelada.</p>

    <div class="card" style="margin-bottom: 24px;">
        <div class="card__header">
            <p class="card__eyebrow">Vendas · Venda #<?= (int)$venda['id'] ?></p>
            <h2 class="card__title">R$ <?= number_format((float)$venda['valor_total'], 2, ',', '.') ?></h2>
            <p class="card__subtitle">
                <?= htmlspecialchars($venda['cliente_nome'] ?: 'Consumidor não identificado') ?> ·
                <?= htmlspecialchars(date('d/m/Y H:i', strtotime($venda['data_venda']))) ?> ·
                <?= htmlspecialchars(strtoupper($venda['forma_pagamento'])) ?>
            </p>
        </div>

        <div class="table-wrap" style="margin-bottom: 18px;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Lote</th>
                        <th>Qtde.</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itens as $i): ?>
                        <tr>
                            <td><?= htmlspecialchars($i['produto_nome']) ?></td>
                            <td><?= htmlspecialchars($i['numero_lote'] ?: '—') ?></td>
                            <td><?= (int)$i['quantidade'] ?></td>
                            <td>R$ <?= number_format((float)$i['subtotal'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($concluido): ?>
            <div class="msg msg--success"><?= htmlspecialchars($mensagemFinal) ?></div>
            <a href="historico.php?aba=vendas" class="btn">Voltar ao histórico</a>
        <?php elseif ($jaCancelada): ?>
            <div class="msg msg--error">Esta venda já está cancelada.</div>
            <a href="historico.php?aba=vendas" class="btn btn--ghost">Voltar ao histórico</a>
        <?php else: ?>
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

                <div class="field-grid">
                    <div class="field-full">
                        <label for="motivo">Motivo do cancelamento</label>
                        <input type="text" id="motivo" name="motivo" value="<?= htmlspecialchars($motivo) ?>" maxlength="200" required>
                    </div>
                </div>

                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn--danger">Cancelar venda</button>
                    <a href="historico.php?aba=vendas" class="btn btn--ghost">Voltar</a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <a href="historico.php?aba=vendas" class="back">← Voltar para o histórico de vendas</a>
</main>

</body>
</html>
