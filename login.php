<?php require_once __DIR__ . '/config/database.php'; require_once __DIR__ . '/auth.php';

if (!empty($_SESSION['usuario_id'])) {
    header('Location: home.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    $pdo = Database::getConnection();
    $stmt = $pdo->prepare('SELECT id, nome, senha_hash, nivel_acesso, ativo FROM usuarios WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $usuario = $stmt->fetch();

    if (!$usuario || !password_verify($senha, $usuario['senha_hash'])) {
        $erro = 'E-mail ou senha incorretos.';
    } elseif (!$usuario['ativo']) {
        $erro = 'Esta conta está inativa. Fale com um administrador.';
    } else {
        session_regenerate_id(true);
        $_SESSION['usuario_id']    = $usuario['id'];
        $_SESSION['usuario_nome']  = $usuario['nome'];
        $_SESSION['usuario_nivel'] = $usuario['nivel_acesso'];

        header('Location: home.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Entrar — WSI</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-page">

<div class="card card--auth">
    <div class="card__header">
        <p class="card__eyebrow">WSI</p>
        <h1 class="card__title">Entrar</h1>
    </div>
    <form method="POST" action="">
        <?php if ($erro): ?>
            <div class="msg msg--error"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        <div>
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" required autofocus>
        </div>
        <div>
            <label for="senha">Senha</label>
            <input type="password" id="senha" name="senha" required>
        </div>
        <button type="submit">Entrar</button>

        <a href="cadastro_usuario.php" class="link-secundario">Ainda não tem cadastro? Criar conta</a>
    </form>
</div>

</body>
</html>
