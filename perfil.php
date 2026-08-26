<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth.php';

exigirLogin();

$pdo = Database::getConnection();

$erros = [];
$sucesso = '';

// Busca os dados atuais do usuário logado
$stmt = $pdo->prepare('SELECT id, nome, email, criado_em FROM usuarios WHERE id = :id');
$stmt->execute(['id' => $_SESSION['usuario_id']]);
$usuario = $stmt->fetch();

if (!$usuario) {
    // Sessão aponta pra um usuário que não existe mais
    header('Location: logout.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // ---------- Atualizar nome / e-mail ----------
    if ($acao === 'dados') {
        $nome  = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($nome === '') {
            $erros[] = 'Informe o nome.';
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'Informe um e-mail válido.';
        }

        if (empty($erros)) {
            // Verifica se o e-mail já está em uso por outro usuário
            $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email AND id != :id');
            $stmt->execute(['email' => $email, 'id' => $usuario['id']]);

            if ($stmt->fetch()) {
                $erros[] = 'Esse e-mail já está sendo usado por outro usuário.';
            } else {
                $stmt = $pdo->prepare('UPDATE usuarios SET nome = :nome, email = :email WHERE id = :id');
                $stmt->execute([
                    'nome'  => $nome,
                    'email' => $email,
                    'id'    => $usuario['id'],
                ]);

                $_SESSION['usuario_nome'] = $nome;
                $usuario['nome']  = $nome;
                $usuario['email'] = $email;
                $sucesso = 'Dados atualizados com sucesso.';
            }
        }
    }

    // ---------- Trocar senha ----------
    if ($acao === 'senha') {
        $senhaAtual   = $_POST['senha_atual'] ?? '';
        $novaSenha    = $_POST['nova_senha'] ?? '';
        $confirmarNova = $_POST['confirmar_nova_senha'] ?? '';

        $stmt = $pdo->prepare('SELECT senha_hash FROM usuarios WHERE id = :id');
        $stmt->execute(['id' => $usuario['id']]);
        $hashAtual = $stmt->fetchColumn();

        if (!password_verify($senhaAtual, $hashAtual)) {
            $erros[] = 'Senha atual incorreta.';
        }

        if (strlen($novaSenha) < 6) {
            $erros[] = 'A nova senha deve ter pelo menos 6 caracteres.';
        }

        if ($novaSenha !== $confirmarNova) {
            $erros[] = 'As senhas não coincidem.';
        }

        if (empty($erros)) {
            $stmt = $pdo->prepare('UPDATE usuarios SET senha_hash = :senha_hash WHERE id = :id');
            $stmt->execute([
                'senha_hash' => password_hash($novaSenha, PASSWORD_DEFAULT),
                'id'         => $usuario['id'],
            ]);

            $sucesso = 'Senha alterada com sucesso.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Meu perfil — WSI</title>
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
        margin: 0; min-height: 100vh;
        background: linear-gradient(180deg, rgba(79,176,165,0.06), transparent 320px), var(--bg);
        color: var(--ink); font-family: var(--sans);
    }
    header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 18px 32px; border-bottom: 1px solid var(--panel-border);
    }
    header .eyebrow {
        font-family: var(--mono); font-size: 11px; letter-spacing: 0.14em;
        text-transform: uppercase; color: var(--accent); margin: 0;
    }
    header nav a {
        color: var(--ink-dim); text-decoration: none; font-size: 13px; margin-left: 18px;
    }
    header nav a:hover { color: var(--accent); }

    main {
        max-width: 520px; margin: 0 auto; padding: 40px 20px 60px;
        display: flex; flex-direction: column; gap: 20px;
    }
    main h1 { font-size: 24px; margin: 0 0 4px; }
    main p.sub { color: var(--ink-dim); margin: 0 0 8px; font-size: 13px; }

    .card {
        background: var(--panel); border: 1px solid var(--panel-border);
        border-radius: var(--radius); overflow: hidden;
    }
    .card__header {
        padding: 16px 24px; border-bottom: 1px solid var(--panel-border);
    }
    .card__eyebrow {
        font-family: var(--mono); font-size: 11px; letter-spacing: 0.1em;
        text-transform: uppercase; color: var(--accent); margin: 0;
    }
    form { padding: 18px 24px 24px; display: flex; flex-direction: column; gap: 14px; }

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
    input:disabled { color: var(--ink-dim); }

    .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

    button {
        margin-top: 4px; background: var(--accent); color: #0b1210; border: none;
        padding: 11px; border-radius: var(--radius); font-size: 14px; font-weight: 600; cursor: pointer;
    }
    button:hover { background: #62c1b6; }

    .meta {
        font-size: 12px; color: var(--ink-dim); padding: 0 24px 18px;
        font-family: var(--mono);
    }

    .msg {
        border-radius: var(--radius); padding: 12px 14px; font-size: 13px; margin-bottom: 4px;
    }
    .msg--success {
        background: rgba(79,176,165,0.12); border: 1px solid var(--accent-dim); color: var(--accent);
    }
    .msg--error {
        background: rgba(226,102,90,0.1); border: 1px solid var(--danger); color: var(--danger);
    }
    .msg--error ul { margin: 4px 0 0; padding-left: 18px; }
</style>
</head>
<body>

<header>
    <p class="eyebrow">WSI</p>
    <nav>
        <a href="home.php">Início</a>
        <a href="logout.php">Sair</a>
    </nav>
</header>

<main>
    <div>
        <h1>Meu perfil</h1>
        <p class="sub">Veja e edite suas informações de acesso.</p>
    </div>

    <?php if ($sucesso): ?>
        <div class="msg msg--success"><?= htmlspecialchars($sucesso) ?></div>
    <?php endif; ?>

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

    <!-- Dados pessoais -->
    <div class="card">
        <div class="card__header">
            <p class="card__eyebrow">Dados pessoais</p>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="acao" value="dados">

            <div>
                <label for="nome">Nome completo</label>
                <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($usuario['nome']) ?>" required>
            </div>

            <div>
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($usuario['email']) ?>" required>
            </div>

            <button type="submit">Salvar alterações</button>
        </form>
        <p class="meta">Cadastrado em <?= htmlspecialchars(date('d/m/Y', strtotime($usuario['criado_em']))) ?></p>
    </div>

    <!-- Trocar senha -->
    <div class="card">
        <div class="card__header">
            <p class="card__eyebrow">Segurança</p>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="acao" value="senha">

            <div>
                <label for="senha_atual">Senha atual</label>
                <input type="password" id="senha_atual" name="senha_atual" required>
            </div>

            <div class="field-row">
                <div>
                    <label for="nova_senha">Nova senha</label>
                    <input type="password" id="nova_senha" name="nova_senha" required minlength="6">
                </div>
                <div>
                    <label for="confirmar_nova_senha">Confirmar nova senha</label>
                    <input type="password" id="confirmar_nova_senha" name="confirmar_nova_senha" required minlength="6">
                </div>
            </div>

            <button type="submit">Alterar senha</button>
        </form>
    </div>
</main>

</body>
</html>