<?php require_once __DIR__ . '/auth.php'; exigirLogin(); require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/partials/movimentacao_helpers.php';

/*
 * EE-29 — Estornar movimentação.
 * Não apaga a movimentação original: cria uma movimentação inversa e corrige o saldo do lote.
 *   - Entrada                              -> estorno é uma saída (tira do lote)
 *   - Saída, ajuste ou perda por vencimento -> estorno é uma entrada (devolve ao lote)
 * Movimentações de venda devem ser desfeitas por "Cancelar venda".
 */

$pdo = Database::getConnection();

$erros = [];
$concluido = false;
$mensagemFinal = '';

$movId = $_GET['id'] ?? $_POST['id'] ?? '';
if ($movId === '' || !ctype_digit((string)$movId)) {
    header('Location: historico.php');
    exit;
}

$sqlMov = 'SELECT m.*, p.nome AS produto_nome, l.numero_lote, u.nome AS usuario_nome
           FROM estoque_movimentacoes m
           JOIN produtos p ON p.id = m.produto_id
           LEFT JOIN lotes l ON l.id = m.lote_id
           LEFT JOIN usuarios u ON u.id = m.usuario_id
           WHERE m.id = :id';

$stmt = $pdo->prepare($sqlMov);
$stmt->execute(['id' => $movId]);
$mov = $stmt->fetch();

if (!$mov) {
    header('Location: historico.php');
    exit;
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM estoque_movimentacoes WHERE motivo LIKE :marca');
$stmt->execute(['marca' => marcaEstorno((int)$mov['id']) . '%']);
$jaEstornada = (int)$stmt->fetchColumn() > 0;

$impedimento = '';
if (motivoReservado($mov['motivo'])) {
    $impedimento = 'Movimentações de venda, de cancelamento ou de estorno não podem ser estornadas por aqui. '
                 . 'Para desfazer uma venda, use "Cancelar venda" no histórico de vendas.';
} elseif ($mov['lote_id'] === null) {
    $impedimento = 'O lote desta movimentação não existe mais, então não há saldo para corrigir.';
} elseif ($jaEstornada) {
    $impedimento = 'Esta movimentação já foi estornada.';
}

$motivo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $impedimento === '') {

    $motivo = trim($_POST['motivo'] ?? '');

    if ($motivo === '') {
        $erros[] = 'Informe o motivo do estorno.';
    }

    if (empty($erros)) {
        try {
            $pdo->beginTransaction();

            // Trava a movimentação e o lote para evitar estorno duplicado ou corrida de saldo
            $stmt = $pdo->prepare('SELECT * FROM estoque_movimentacoes WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $mov['id']]);
            $original = $stmt->fetch();

            $stmt = $pdo->prepare('SELECT * FROM lotes WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $mov['lote_id']]);
            $lote = $stmt->fetch();

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM estoque_movimentacoes WHERE motivo LIKE :marca');
            $stmt->execute(['marca' => marcaEstorno((int)$mov['id']) . '%']);

            if (!$original || !$lote) {
                $erros[] = 'Movimentação ou lote não encontrado.';
            } elseif ((int)$stmt->fetchColumn() > 0) {
                $erros[] = 'Esta movimentação já foi estornada.';
            } elseif ($original['tipo'] === 'entrada' && (int)$lote['quantidade_disponivel'] < (int)$original['quantidade']) {
                $erros[] = 'Não é possível estornar: o lote tem apenas ' . (int)$lote['quantidade_disponivel']
                         . ' unidade(s) disponível(is) e a entrada foi de ' . (int)$original['quantidade'] . '.';
            }

            if (empty($erros)) {
                $eEntrada = $original['tipo'] === 'entrada';

                $stmt = $pdo->prepare(
                    'UPDATE lotes
                     SET quantidade_disponivel = quantidade_disponivel ' . ($eEntrada ? '-' : '+') . ' :quantidade
                     WHERE id = :id'
                );
                $stmt->execute(['quantidade' => $original['quantidade'], 'id' => $lote['id']]);

                $stmt = $pdo->prepare(
                    'INSERT INTO estoque_movimentacoes
                        (produto_id, lote_id, tipo, quantidade, motivo, usuario_id)
                     VALUES
                        (:produto_id, :lote_id, :tipo, :quantidade, :motivo, :usuario_id)'
                );
                $stmt->execute([
                    'produto_id' => $original['produto_id'],
                    'lote_id'    => $lote['id'],
                    'tipo'       => $eEntrada ? 'saida' : 'entrada',
                    'quantidade' => $original['quantidade'],
                    'motivo'     => mb_substr(marcaEstorno((int)$original['id']) . ' ' . $motivo, 0, 255),
                    'usuario_id' => $_SESSION['usuario_id'],
                ]);

                $pdo->commit();

                $concluido = true;
                $mensagemFinal = 'Movimentação #' . (int)$original['id'] . ' estornada com sucesso.';
            } else {
                $pdo->rollBack();
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erros[] = 'Não foi possível estornar a movimentação.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Estornar Movimentação — WSI</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>

<main class="wide">
    <h1>Estornar movimentação</h1>
    <p class="sub">O estorno cria uma movimentação inversa e corrige o saldo do lote. O registro original continua no histórico.</p>

    <div class="card" style="margin-bottom: 24px;">
        <div class="card__header">
            <p class="card__eyebrow">Histórico · Movimentação #<?= (int)$mov['id'] ?></p>
            <h2 class="card__title"><?= htmlspecialchars($mov['produto_nome']) ?></h2>
            <p class="card__subtitle">
                <?= htmlspecialchars(str_replace('_', ' ', $mov['tipo'])) ?> ·
                <?= (int)$mov['quantidade'] ?> un. ·
                Lote <?= htmlspecialchars($mov['numero_lote'] ?: '—') ?> ·
                <?= htmlspecialchars(date('d/m/Y H:i', strtotime($mov['data_movimentacao']))) ?> ·
                <?= htmlspecialchars($mov['usuario_nome'] ?: '—') ?>
            </p>
        </div>

        <?php if ($concluido): ?>
            <div class="msg msg--success"><?= htmlspecialchars($mensagemFinal) ?></div>
            <a href="historico.php" class="btn">Voltar ao histórico</a>
        <?php elseif ($impedimento !== ''): ?>
            <div class="msg msg--error"><?= htmlspecialchars($impedimento) ?></div>
            <a href="historico.php" class="btn btn--ghost">Voltar ao histórico</a>
        <?php else: ?>
            <form method="POST" action="" class="stack" novalidate>
                <input type="hidden" name="id" value="<?= (int)$mov['id'] ?>">

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
                        <label for="motivo">Motivo do estorno</label>
                        <input type="text" id="motivo" name="motivo" value="<?= htmlspecialchars($motivo) ?>" maxlength="200" required>
                    </div>
                </div>

                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn--danger">Estornar movimentação</button>
                    <a href="historico.php" class="btn btn--ghost">Cancelar</a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <a href="historico.php" class="back">← Voltar para o histórico</a>
</main>

</body>
</html>
