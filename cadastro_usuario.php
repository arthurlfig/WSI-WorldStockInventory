<?php
require_once __DIR__ . '/config/database.php';

$erros = [];
$valores = ['nome' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valores['nome']  = trim($_POST['nome'] ?? '');
    $valores['email'] = trim($_POST['email'] ?? '');
    $senha            = $_POST['senha'] ?? '';
    $confirmarSenha   = $_POST['confirmar_senha'] ?? '';

    // Validações
    if ($valores['nome'] === '') {
        $erros[] = 'Informe o nome.';
    }

    if ($valores['email'] === '' || !filter_var($valores['email'], FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'Informe um e-mail válido.';
    }

    if (strlen($senha) < 6) {
        $erros[] = 'A senha deve ter pelo menos 6 caracteres.';
    }

    if ($senha !== $confirmarSenha) {
        $erros[] = 'As senhas não coincidem.';
    }

    // Se passou nas validações básicas, checa duplicidade e insere
    if (empty($erros)) {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email');
        $stmt->execute(['email' => $valores['email']]);

        if ($stmt->fetch()) {
            $erros[] = 'Já existe um usuário cadastrado com esse e-mail.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO usuarios (nome, email, senha_hash)
                 VALUES (:nome, :email, :senha_hash)'
            );
            $stmt->execute([
                'nome'       => $valores['nome'],
                'email'      => $valores['email'],
                'senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
            ]);

            header('Location: login.php?cadastrado=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cadastro de Usuário — Gestão de Estoque</title>
<style>
    :root {
        --bg: #14181f;
        --panel: #1c222c;
        --panel-border: #2b3340;
        --ink: #e8ebf0;
        --ink-dim: #8b96a8;
        --accent: #4fb0a5;
        --accent-dim: #35766f;
        --danger: #e2665a;
        --field-bg: #10141a;
        --radius: 6px;
        --mono: 'JetBrains Mono', 'Courier New', monospace;
        --sans: 'Inter', 'Segoe UI', system-ui, sans-serif;
    }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        min-height: 100vh;
        background:
            linear-gradient(180deg, rgba(79,176,165,0.06), transparent 320px),
            var(--bg);
        color: var(--ink);
        font-family: var(--sans);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 32px 16px;
    }

    .card {
        width: 100%;
        max-width: 440px;
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: var(--radius);
        overflow: hidden;
    }

    .card__header {
        padding: 22px 28px 18px;
        border-bottom: 1px solid var(--panel-border);
    }

    .card__eyebrow {
        font-family: var(--mono);
        font-size: 11px;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: var(--accent);
        margin: 0 0 6px;
    }

    .card__title {
        margin: 0;
        font-size: 20px;
        font-weight: 600;
        letter-spacing: -0.01em;
    }

    form {
        padding: 22px 28px 28px;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    label {
        font-size: 12px;
        color: var(--ink-dim);
        display: block;
        margin-bottom: 6px;
        font-family: var(--mono);
        letter-spacing: 0.02em;
    }

    input[type="text"],
    input[type="email"],
    input[type="password"] {
        width: 100%;
        background: var(--field-bg);
        border: 1px solid var(--panel-border);
        color: var(--ink);
        padding: 10px 12px;
        border-radius: var(--radius);
        font-size: 14px;
        font-family: var(--sans);
        transition: border-color 0.15s ease;
    }

    input:focus {
        outline: none;
        border-color: var(--accent);
    }

    .field-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    button {
        margin-top: 4px;
        background: var(--accent);
        color: #0b1210;
        border: none;
        padding: 12px;
        border-radius: var(--radius);
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.15s ease;
    }

    button:hover { background: #62c1b6; }

    .msg {
        border-radius: var(--radius);
        padding: 12px 14px;
        font-size: 13px;
        margin-bottom: 4px;
    }

    .msg--success {
        background: rgba(79,176,165,0.12);
        border: 1px solid var(--accent-dim);
        color: var(--accent);
    }

    .msg--error {
        background: rgba(226,102,90,0.1);
        border: 1px solid var(--danger);
        color: var(--danger);
    }

    .msg--error ul {
        margin: 4px 0 0;
        padding-left: 18px;
    }

    .hint {
        font-size: 11px;
        color: var(--ink-dim);
        margin-top: -8px;
    }
</style>
</head>
<body>

<div class="card">
    <div class="card__header">
        <p class="card__eyebrow">Usuários · Novo registro</p>
        <h1 class="card__title">Cadastro de usuário</h1>
    </div>

    <form method="POST" action="" novalidate>

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

        <div>
            <label for="nome">Nome completo</label>
            <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($valores['nome']) ?>" required>
        </div>

        <div>
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($valores['email']) ?>" required>
        </div>

        <div class="field-row">
            <div>
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" required minlength="6">
            </div>
            <div>
                <label for="confirmar_senha">Confirmar senha</label>
                <input type="password" id="confirmar_senha" name="confirmar_senha" required minlength="6">
            </div>
        </div>
        <p class="hint">Mínimo de 6 caracteres.</p>

        <button type="submit">Cadastrar usuário</button>
    </form>
</div>

</body>
</html>