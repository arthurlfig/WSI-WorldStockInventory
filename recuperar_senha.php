<?php require_once __DIR__ . '/config/database.php'; require_once __DIR__ . '/auth.php';

if (!empty($_SESSION['usuario_id'])) {
    header('Location: home.php');
    exit;
}

$erro = '';
$linkRedefinicao = '';

/* Mensagem sempre igual, exista ou não o e-mail - evita que alguém descubra quais e-mails têm conta só testando este formulário. */

$mensagemPadrao = 'Se existir uma conta com esse e-mail, um link de redefinição de senha foi gerado.';
$mostrarMensagemPadrao = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } else {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('SELECT id, ativo FROM usuarios WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $usuario = $stmt->fetch();

        if ($usuario && $usuario['ativo']) {
            $token = bin2hex(random_bytes(32));

            $expiraEm = date('Y-m-d H:i:s', time() + 3600);

            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO password_resets (usuario_id, token, expira_em)
                     VALUES (:usuario_id, :token, :expira_em)'
                );
                $stmt->execute([
                    'usuario_id' => $usuario['id'],
                    'token' => $token,
                    'expira_em' => $expiraEm,
                ]);

                $linkRedefinicao = 'redefinir_senha.php?token=' . urlencode($token);
            } catch (PDOException $e) {

                $erro = 'A recuperação de senha ainda não está configurada neste ambiente (tabela password_resets ausente). Peça para um administrador rodar a migração do banco de dados.';
                $mostrarMensagemPadrao = false;
            }
        }

        if ($erro === '') {
            $mostrarMensagemPadrao = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Recuperar senha — WSI</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-page">

<div class="card card--auth">
    <div class="card__header">
        <p class="card__eyebrow">WSI</p>
        <h1 class="card__title">Recuperar senha</h1>
    </div>

    <?php if ($mostrarMensagemPadrao): ?>

        <div class="msg msg--success"><?= htmlspecialchars($mensagemPadrao) ?></div>

        <?php if ($linkRedefinicao !== ''): ?>
            <div class="msg" style="border: 1px dashed var(--panel-border); font-size: 13px;">
                <strong>Ambiente sem envio de e-mail configurado.</strong>
                Em produção este link seria enviado por e-mail. Por enquanto, use-o diretamente:
                <br><br>
                <a href="<?= htmlspecialchars($linkRedefinicao) ?>"><?= htmlspecialchars($linkRedefinicao) ?></a>
                <br><br>
                O link expira em 1 hora.
            </div>
        <?php endif; ?>

        <a href="login.php" class="link-secundario">Voltar para o login</a>

    <?php else: ?>

        <form method="POST" action="">
            <?php if ($erro): ?>
                <div class="msg msg--error"><?= htmlspecialchars($erro) ?></div>
            <?php endif; ?>

            <p class="sub" style="margin-top: 0;">Informe o e-mail da sua conta. Vamos gerar um link para você criar uma nova senha.</p>

            <div>
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" required autofocus>
            </div>

            <button type="submit">Enviar link de redefinição</button>

            <a href="login.php" class="link-secundario">Voltar para o login</a>
        </form>

    <?php endif; ?>
</div>

</body>
</html>