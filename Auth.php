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