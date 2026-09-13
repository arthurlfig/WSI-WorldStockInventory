<?php require_once __DIR__ . '/config/database.php'; require_once __DIR__ . '/auth.php';

if (!empty($_SESSION['usuario_id'])) {
    header('Location: home.php');
    exit;
}

$pdo = Database::getConnection();

$token = $_POST['token'] ?? $_GET['token'] ?? '';
$erro = '';
$sucesso = '';

$resetValido = null;

if ($token !== '') {
    $stmt = $pdo->prepare(
        'SELECT pr.id, pr.usuario_id, pr.expira_em, pr.usado
         FROM password_resets pr
         WHERE pr.token = :token'
    );
    $stmt->execute(['token' => $token]);
    $resetValido = $stmt->fetch();
}

$tokenValido = $resetValido
    && !$resetValido['usado']
    && strtotime($resetValido['expira_em']) >= time();

if ($token === '' || !$resetValido) {
    $erro = 'Link de redefinição inválido.';
} elseif (!$tokenValido) {
    $erro = $resetValido['usado']
        ? 'Este link já foi utilizado.'
        : 'Este link expirou. Solicite uma nova redefinição de senha.';
}

if ($tokenValido && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['senha'])) {
    $senha = $_POST['senha'] ?? '';
    $confirmarSenha = $_POST['confirmar_senha'] ?? '';

    if (strlen($senha) < 6) {
        $erro = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($senha !== $confirmarSenha) {
        $erro = 'As senhas não coincidem.';
    } else {
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('UPDATE usuarios SET senha_hash = :senha_hash WHERE id = :id');
            $stmt->execute([
                'senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
                'id' => $resetValido['usuario_id'],
            ]);

            $stmt = $pdo->prepare('UPDATE password_resets SET usado = 1 WHERE id = :id');
            $stmt->execute(['id' => $resetValido['id']]);

            $pdo->commit();

            $sucesso = 'Senha redefinida com sucesso! Você já pode entrar com a nova senha.';
            $tokenValido = false;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $erro = 'Não foi possível redefinir a senha.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Redefinir senha — WSI</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-page">

<div class="card card--auth">
    <div class="card__header">
        <p class="card__eyebrow">WSI</p>
        <h1 class="card__title">Redefinir senha</h1>
    </div>

    <?php if ($sucesso): ?>

        <div class="msg msg--success"><?= htmlspecialchars($sucesso) ?></div>
        <a href="login.php" class="link-secundario">Ir para o login</a>

    <?php elseif (!$tokenValido): ?>

        <div class="msg msg--error"><?= htmlspecialchars($erro) ?></div>
        <a href="recuperar_senha.php" class="link-secundario">Solicitar novo link</a>

    <?php else: ?>

        <form method="POST" action="">
            <?php if ($erro): ?>
                <div class="msg msg--error"><?= htmlspecialchars($erro) ?></div>
            <?php endif; ?>

            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <div>
                <label for="senha">Nova senha</label>
                <input type="password" id="senha" name="senha" required minlength="6" autofocus>
            </div>

            <div>
                <label for="confirmar_senha">Confirmar nova senha</label>
                <input type="password" id="confirmar_senha" name="confirmar_senha" required minlength="6">
            </div>

            <button type="submit">Redefinir senha</button>

            <a href="login.php" class="link-secundario">Voltar para o login</a>
        </form>

    <?php endif; ?>
</div>

</body>
</html>