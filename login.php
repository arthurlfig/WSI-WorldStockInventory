<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth.php';

if (!empty($_SESSION['usuario_id'])) {
    header('Location: home.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    $pdo = Database::getConnection();
    $stmt = $pdo->prepare('SELECT id, nome, senha_hash FROM usuarios WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $usuario = $stmt->fetch();

    if (!$usuario || !password_verify($senha, $usuario['senha_hash'])) {
        $erro = 'E-mail ou senha incorretos.';
    } else {
        session_regenerate_id(true);
        $_SESSION['usuario_id']   = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];

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
<title>Entrar — Gestão de Estoque</title>
<style>
    :root {
        --bg: #14181f;
        --panel: #1c222c;
        --panel-border: #2b3340;
        --ink: #e8ebf0;
        --ink-dim: #8b96a8;
        --accent: #4fb0a5;
        --danger: #e2665a;
        --field-bg: #10141a;
        --radius: 6px;
        --mono: 'JetBrains Mono', 'Courier New', monospace;
        --sans: 'Inter', 'Segoe UI', system-ui, sans-serif;
    }
    * { box-sizing: border-box; }
    body {
        margin: 0; min-height: 100vh;
        background: linear-gradient(180deg, rgba(79,176,165,0.06), transparent 320px), var(--bg);
        color: var(--ink); font-family: var(--sans);
        display: flex; align-items: center; justify-content: center; padding: 32px 16px;
    }
    .card {
        width: 100%; max-width: 380px;
        background: var(--panel); border: 1px solid var(--panel-border);
        border-radius: var(--radius); overflow: hidden;
    }
    .card__header { padding: 22px 28px 18px; border-bottom: 1px solid var(--panel-border); }
    .card__eyebrow {
        font-family: var(--mono); font-size: 11px; letter-spacing: 0.14em;
        text-transform: uppercase; color: var(--accent); margin: 0 0 6px;
    }
    .card__title { margin: 0; font-size: 20px; font-weight: 600; letter-spacing: -0.01em; }
    form { padding: 22px 28px 28px; display: flex; flex-direction: column; gap: 16px; }
    label {
        font-size: 12px; color: var(--ink-dim); display: block; margin-bottom: 6px;
        font-family: var(--mono); letter-spacing: 0.02em;
    }
    input {
        width: 100%; background: var(--field-bg); border: 1px solid var(--panel-border);
        color: var(--ink); padding: 10px 12px; border-radius: var(--radius);
        font-size: 14px; font-family: var(--sans);
    }
    input:focus { outline: none; border-color: var(--accent); }
    button {
        margin-top: 4px; background: var(--accent); color: #0b1210; border: none;
        padding: 12px; border-radius: var(--radius); font-size: 14px; font-weight: 600; cursor: pointer;
    }
    button:hover { background: #62c1b6; }
    .msg--error {
        background: rgba(226,102,90,0.1); border: 1px solid var(--danger); color: var(--danger);
        border-radius: var(--radius); padding: 12px 14px; font-size: 13px;
    }
    .link-secundario {
        text-align: center;
        font-size: 13px;
        color: var(--ink-dim);
        text-decoration: none;
        margin-top: 2px;
    }
    .link-secundario:hover { color: var(--accent); }
</style>
</head>
<body>
<div class="card">
    <div class="card__header">
        <p class="card__eyebrow">Gestão de Estoque</p>
        <h1 class="card__title">Entrar</h1>
    </div>
    <form method="POST" action="">
        <?php if ($erro): ?>
            <div class="msg--error"><?= htmlspecialchars($erro) ?></div>
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