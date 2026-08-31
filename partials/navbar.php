<?php
// Espera que auth.php já tenha rodado exigirLogin() antes deste include
$paginaAtual = basename($_SERVER['PHP_SELF']);

function navLinkClass($arquivo, $paginaAtual) {
    return $arquivo === $paginaAtual ? 'nav-link ativo' : 'nav-link';
}
?>
<header>
    <div style="display:flex; align-items:center; gap:28px;">
        <p class="eyebrow" style="margin:0;">WSI</p>
        <nav style="display:flex; gap:18px;">
            <a class="<?= navLinkClass('home.php', $paginaAtual) ?>" href="home.php">Início</a>
            <a class="<?= navLinkClass('cadastro_usuario.php', $paginaAtual) ?>" href="cadastro_usuario.php">Usuários</a>
            <a class="<?= navLinkClass('cadastro_produto.php', $paginaAtual) ?>" href="cadastro_produto.php">Produtos</a>
        </nav>
    </div>
    <div>
        <span style="color: var(--ink-dim); font-size: 13px; margin-right: 14px;">
            Olá, <?= htmlspecialchars($_SESSION['usuario_nome']) ?>
        </span>
        <a href="perfil.php" style="margin-right: 14px;">Meu perfil</a>
        <a href="logout.php">Sair</a>
    </div>
</header>