<?php require_once __DIR__ . '/auth.php'; exigirLogin(); require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/partials/movimentacao_helpers.php';

/*
 * EE-28 — Alterar movimentação.
 * Só o motivo pode ser corrigido. Tipo, quantidade e lote não mudam aqui,
 * porque alterariam o saldo; para isso existe o estorno.
 */

$pdo = Database::getConnection();

$erros = [];
$sucesso = '';

$movId = $_GET['id'] ?? $_POST['id'] ?? '';
if ($movId === '' || !ctype_digit((string)$movId)) {
    header('Location: historico.php');
    exit;
}

function carregarMovimentacao(PDO $pdo, string $id)
{
    $stmt = $pdo->prepare(
        'SELECT m.*, p.nome AS produto_nome, l.numero_lote, u.nome AS usuario_nome
         FROM estoque_movimentacoes m
         JOIN produtos p ON p.id = m.produto_id
         LEFT JOIN lotes l ON l.id = m.lote_id
         LEFT JOIN usuarios u ON u.id = m.usuario_id
         WHERE m.id = :id'
    );
    $stmt->execute(['id' => $id]);
    return $stmt->fetch();
}

$mov = carregarMovimentacao($pdo, (string)$movId);
if (!$mov) {
    header('Location: historico.php');
    exit;
}

$bloqueada = motivoReservado($mov['motivo']);
$motivo = (string)($mov['motivo'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$bloqueada) {

    $motivo = trim($_POST['motivo'] ?? '');

    if ($motivo === '') {
        $erros[] = 'Informe o motivo da movimentação.';
    } elseif (motivoReservado($motivo)) {
        $erros[] = 'Esse texto é reservado pelo sistema (venda, estorno ou cancelamento). Escreva outro motivo.';
    }

    if (empty($erros)) {
        try {
            $stmt = $pdo->prepare('UPDATE estoque_movimentacoes SET motivo = :motivo WHERE id = :id');
            $stmt->execute([
                'motivo' => mb_substr($motivo, 0, 255),
                'id'     => $mov['id'],
            ]);

            $sucesso = 'Motivo da movimentação #' . (int)$mov['id'] . ' atualizado com sucesso!';
            $mov = carregarMovimentacao($pdo, (string)$mov['id']);
        } catch (PDOException $e) {
            $erros[] = 'Não foi possível atualizar a movimentação.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Alterar Movimentação — WSI</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>

<main class="wide">
    <h1>Alterar movimentação</h1>
    <p class="sub">Corrija o motivo registrado. Tipo e quantidade não podem ser alterados; para desfazer uma movimentação, use o estorno.</p>

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

        <?php if ($bloqueada): ?>
            <div class="msg msg--error">
                Esta movimentação foi gerada por uma venda, estorno ou cancelamento e não pode ter o motivo alterado.
            </div>
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

                <?php if ($sucesso): ?>
                    <div class="msg msg--success"><?= htmlspecialchars($sucesso) ?></div>
                <?php endif; ?>

                <div class="field-grid">
                    <div class="field-full">
                        <label for="motivo">Motivo</label>
                        <input type="text" id="motivo" name="motivo" value="<?= htmlspecialchars($motivo) ?>" maxlength="255" required>
                    </div>
                </div>

                <div style="display:flex; gap:10px;">
                    <button type="submit">Salvar alterações</button>
                    <a href="historico.php" class="btn btn--ghost">Voltar</a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <a href="historico.php" class="back">← Voltar para o histórico</a>
</main>

</body>
</html>
