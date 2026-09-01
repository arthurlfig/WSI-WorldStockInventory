<?php require_once __DIR__ . '/config/database.php'; require_once __DIR__ . '/auth.php';

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
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>

<main class="main--narrow">
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