<?php require_once __DIR__ . '/auth.php'; exigirLogin(); require_once __DIR__ . '/config/database.php';

/*
 * EE-26 — Excluir lote.
 * Só exclui lotes que nunca foram vendidos (para não perder o histórico de vendas).
 * O saldo que ainda existia fica registrado como uma movimentação de ajuste.
 */

$pdo = Database::getConnection();

$erros = [];
$concluido = false;
$mensagemFinal = '';

$loteId = $_GET['id'] ?? $_POST['id'] ?? '';
if ($loteId === '' || !ctype_digit((string)$loteId)) {
    header('Location: cadastro_entrada.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT l.*, p.nome AS produto_nome,
            (SELECT COUNT(*) FROM venda_itens vi WHERE vi.lote_id = l.id) AS total_vendas
     FROM lotes l
     JOIN produtos p ON p.id = l.produto_id
     WHERE l.id = :id'
);
$stmt->execute(['id' => $loteId]);
$lote = $stmt->fetch();

if (!$lote) {
    header('Location: cadastro_entrada.php');
    exit;
}

$motivo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $motivo = trim($_POST['motivo'] ?? '');

    if ($motivo === '') {
        $erros[] = 'Informe o motivo da exclusão do lote.';
    }

    if (empty($erros)) {
        try {
            $pdo->beginTransaction();

            // Trava o lote para ninguém vender/dar saída enquanto ele é excluído
            $stmt = $pdo->prepare('SELECT * FROM lotes WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $lote['id']]);
            $loteAtual = $stmt->fetch();

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM venda_itens WHERE lote_id = :id');
            $stmt->execute(['id' => $lote['id']]);
            $vendas = (int)$stmt->fetchColumn();

            if (!$loteAtual) {
                $erros[] = 'Lote não encontrado.';
            } elseif ($vendas > 0) {
                $erros[] = 'Este lote está ligado a ' . $vendas . ' item(ns) de venda e não pode ser excluído. '
                         . 'Para tirá-lo do estoque, registre uma saída de ajuste em Saídas.';
            }

            if (empty($erros)) {
                if ((int)$loteAtual['quantidade_disponivel'] > 0) {
                    $stmt = $pdo->prepare(
                        "INSERT INTO estoque_movimentacoes
                            (produto_id, lote_id, tipo, quantidade, motivo, usuario_id)
                         VALUES
                            (:produto_id, NULL, 'ajuste', :quantidade, :motivo, :usuario_id)"
                    );
                    $stmt->execute([
                        'produto_id' => $loteAtual['produto_id'],
                        'quantidade' => $loteAtual['quantidade_disponivel'],
                        'motivo'     => mb_substr('Exclusão do lote ' . $loteAtual['numero_lote'] . ': ' . $motivo, 0, 255),
                        'usuario_id' => $_SESSION['usuario_id'],
                    ]);
                }

                $stmt = $pdo->prepare('DELETE FROM lotes WHERE id = :id');
                $stmt->execute(['id' => $loteAtual['id']]);

                $pdo->commit();

                $concluido = true;
                $mensagemFinal = 'Lote ' . $loteAtual['numero_lote'] . ' excluído com sucesso.';
            } else {
                $pdo->rollBack();
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erros[] = 'Não foi possível excluir o lote.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Excluir Lote — WSI</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>

<main class="wide">
    <h1>Excluir lote</h1>
    <p class="sub">Confirme os dados do lote e informe o motivo da exclusão.</p>

    <div class="card" style="margin-bottom: 24px;">
        <div class="card__header">
            <p class="card__eyebrow">Estoque · Lote #<?= (int)$lote['id'] ?></p>
            <h2 class="card__title"><?= htmlspecialchars($lote['produto_nome']) ?> — Lote <?= htmlspecialchars($lote['numero_lote']) ?></h2>
            <p class="card__subtitle">
                Recebido: <?= (int)$lote['quantidade_recebida'] ?> un. ·
                Disponível: <?= (int)$lote['quantidade_disponivel'] ?> un. ·
                Itens de venda ligados: <?= (int)$lote['total_vendas'] ?>
            </p>
        </div>

        <?php if ($concluido): ?>
            <div class="msg msg--success"><?= htmlspecialchars($mensagemFinal) ?></div>
            <a href="cadastro_entrada.php" class="btn">Voltar para entradas</a>
        <?php else: ?>
            <form method="POST" action="" class="stack" novalidate>
                <input type="hidden" name="id" value="<?= (int)$lote['id'] ?>">

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

                <?php if ((int)$lote['quantidade_disponivel'] > 0): ?>
                    <div class="msg msg--error">
                        Este lote ainda tem <?= (int)$lote['quantidade_disponivel'] ?> unidade(s) em estoque. Ao excluir,
                        o saldo será baixado e registrado no histórico como ajuste.
                    </div>
                <?php endif; ?>

                <div class="field-grid">
                    <div class="field-full">
                        <label for="motivo">Motivo da exclusão</label>
                        <input type="text" id="motivo" name="motivo" value="<?= htmlspecialchars($motivo) ?>" maxlength="255" required>
                    </div>
                </div>

                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn--danger">Excluir lote</button>
                    <a href="cadastro_entrada.php" class="btn btn--ghost">Cancelar</a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <a href="cadastro_entrada.php" class="back">← Voltar para entradas</a>
</main>

</body>
</html>
