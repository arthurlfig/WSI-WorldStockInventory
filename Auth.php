<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Se não estiver logado, manda pro login.
 */
function exigirLogin(): void
{
    if (empty($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit;
    }
}

function exigirAdmin(): void
{
    exigirLogin();

    if (($_SESSION['usuario_nivel'] ?? '') !== 'admin') {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
           . '<title>Acesso restrito — WSI</title>'
           . '<link rel="stylesheet" href="assets/css/app.css"></head><body>'
           . '<main style="max-width:520px; margin:80px auto; text-align:center;">'
           . '<h1>Acesso restrito</h1>'
           . '<p class="sub">Esta área é exclusiva para administradores.</p>'
           . '<a href="home.php" class="btn">Voltar para o início</a>'
           . '</main></body></html>';
        exit;
    }
}

/**
 * Atalho para checar o nível na hora de esconder/mostrar itens de UI
 * (ex: links do menu) sem cortar o acesso à página inteira.
 */
function usuarioEhAdmin(): bool
{
    return ($_SESSION['usuario_nivel'] ?? '') === 'admin';
}
