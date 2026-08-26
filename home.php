<?php
require_once __DIR__ . '/auth.php';
exigirLogin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Início — Gestão de Estoque</title>
<style>
    :root {
        --bg: #14181f;
        --panel: #1c222c;
        --panel-border: #2b3340;
        --ink: #e8ebf0;
        --ink-dim: #8b96a8;
        --accent: #4fb0a5;
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
    header a {
        color: var(--ink-dim); text-decoration: none; font-size: 13px;
    }
    header a:hover { color: var(--accent); }
    main {
        max-width: 900px; margin: 0 auto; padding: 40px 32px;
    }
    main h1 { font-size: 26px; margin: 0 0 6px; }
    main p.sub { color: var(--ink-dim); margin: 0 0 32px; }
    .grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 16px;
    }
    .tile {
        background: var(--panel); border: 1px solid var(--panel-border);
        border-radius: var(--radius); padding: 20px; text-decoration: none; color: var(--ink);
        transition: border-color 0.15s ease;
    }
    .tile:hover { border-color: var(--accent); }
    .tile .tile-eyebrow {
        font-family: var(--mono); font-size: 11px; color: var(--accent); margin: 0 0 8px;
        text-transform: uppercase; letter-spacing: 0.08em;
    }
    .tile h2 { font-size: 16px; margin: 0 0 6px; }
    .tile p { font-size: 13px; color: var(--ink-dim); margin: 0; }
</style>
</head>
<body>

<header>
    <p class="eyebrow">Gestão de Estoque</p>
    <div>
        <span style="color: var(--ink-dim); font-size: 13px; margin-right: 14px;">
            Olá, <?= htmlspecialchars($_SESSION['usuario_nome']) ?>
        </span>
        <a href="perfil.php" style="margin-right: 14px;">Meu perfil</a>
        <a href="logout.php">Sair</a>
    </div>
</header>

<main>
    <h1>Início</h1>
    <p class="sub">Escolha o que você quer gerenciar.</p>

    <div class="grid">
        <a class="tile" href="cadastro_usuario.php">
            <p class="tile-eyebrow">Usuários</p>
            <h2>Cadastrar usuário</h2>
            <p>Adicionar um novo gestor ao sistema.</p>
        </a>

        <a class="tile" href="cadastro_produto.php">
            <p class="tile-eyebrow">Produtos</p>
            <h2>Cadastrar produto</h2>
            <p>Roupas, cosméticos, brinquedos, jogos ou filmes.</p>
        </a>

        <a class="tile" href="#">
            <p class="tile-eyebrow">Em breve</p>
            <h2>Roupas, brinquedos, jogos, filmes</h2>
            <p>Outras categorias do estoque.</p>
        </a>
    </div>
</main>

</body>
</html>