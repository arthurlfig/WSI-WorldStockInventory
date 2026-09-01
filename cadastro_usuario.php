<?php require_once __DIR__ . '/config/database.php';

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
                'INSERT INTO usuarios (nome, email, senha_hash, nivel_acesso)
                 VALUES (:nome, :email, :senha_hash, \'operador\')'
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
<title>Cadastro de Usuário — WSI</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-page">

<div class="card card--auth">
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
        <p class="hint">A conta é criada como <strong>Operador</strong>. Um administrador pode promovê-la em Consulta de Contas.</p>

        <button type="submit">Cadastrar usuário</button>

        <a href="login.php" class="link-secundario">Já tem conta? Entrar</a>
    </form>
</div>

</body>
</html>