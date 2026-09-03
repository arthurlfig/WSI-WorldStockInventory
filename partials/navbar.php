<?php
// Espera que auth.php já tenha rodado exigirLogin() antes deste include
$paginaAtual = basename($_SERVER['PHP_SELF']);
$souAdmin = usuarioEhAdmin();

function navLinkClass($arquivo, $paginaAtual) {
    return $arquivo === $paginaAtual ? 'nav-link ativo' : 'nav-link';
}
?>
<header>
    <div style="display:flex; align-items:center; gap:28px; flex-wrap: wrap;">
        <p class="eyebrow" style="margin:0;">WSI</p>
        <nav style="display:flex; gap:14px; flex-wrap: wrap; row-gap: 6px;">
            <a class="<?= navLinkClass('home.php', $paginaAtual) ?>" href="home.php">Início</a>
            <a class="<?= navLinkClass('dashboard.php', $paginaAtual) ?>" href="dashboard.php">Dashboard</a>
            <a class="<?= navLinkClass('configuracoes.php', $paginaAtual) ?>" href="configuracoes.php">Configurações</a>
            <a class="<?= navLinkClass('notificacoes.php', $paginaAtual) ?>" href="notificacoes.php">Notificações</a>
            <a class="<?= navLinkClass('produtos.php', $paginaAtual) ?>" href="produtos.php">Produtos</a>
            <a class="<?= navLinkClass('cadastro_entrada.php', $paginaAtual) ?>" href="cadastro_entrada.php">Entradas</a>
            <a class="<?= navLinkClass('cadastro_saida.php', $paginaAtual) ?>" href="cadastro_saida.php">Saídas</a>
            <a class="<?= navLinkClass('cadastro_venda.php', $paginaAtual) ?>" href="cadastro_venda.php">Vendas</a>
            <a class="<?= navLinkClass('historico.php', $paginaAtual) ?>" href="historico.php">Histórico</a>
            <?php if ($souAdmin): ?>
                <a class="<?= navLinkClass('cadastro_fornecedor.php', $paginaAtual) ?>" href="cadastro_fornecedor.php">Fornecedores</a>
                <a class="<?= navLinkClass('consulta_contas.php', $paginaAtual) ?>" href="consulta_contas.php">Contas</a>
            <?php endif; ?>
        </nav>
    </div>
    <div>
        <span style="color: var(--ink-dim); font-size: 13px; margin-right: 14px;">
            Olá, <?= htmlspecialchars($_SESSION['usuario_nome']) ?>
            <span class="badge <?= $souAdmin ? 'badge--ok' : 'badge--muted' ?>" style="margin-left:6px;">
                <?= $souAdmin ? 'admin' : 'operador' ?>
            </span>
        </span>
        <a href="perfil.php" style="margin-right: 14px;">Meu perfil</a>
        <a href="logout.php">Sair</a>
    </div>
</header>
