<?php require_once __DIR__ . '/auth.php'; exigirLogin(); ?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Início — WSI</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>

<main class="wide">
    <h1>Início</h1>
    <p class="sub">Escolha o que você quer gerenciar.</p>

    <div class="grid">
        <a class="tile" href="dashboard.php">
            <p class="tile-eyebrow">Visão geral</p>
            <h2>Dashboard</h2>
            <p>Resumo do estoque, vencimentos e vendas.</p>
        </a>

        <a class="tile" href="produtos.php">
            <p class="tile-eyebrow">Produtos</p>
            <h2>Catálogo de produtos</h2>
            <p>Navegue por categoria com os detalhes de cada item.</p>
        </a>

        <a class="tile" href="consulta_produtos.php">
            <p class="tile-eyebrow">Produtos</p>
            <h2>Fornecedor, lotes e validade</h2>
            <p>Rastreabilidade de cada produto importado.</p>
        </a>

        <a class="tile" href="cadastro_produto.php">
            <p class="tile-eyebrow">Produtos</p>
            <h2>Cadastrar produto</h2>
            <p>Roupas, cosméticos, brinquedos, jogos ou filmes.</p>
        </a>

        <a class="tile" href="cadastro_entrada.php">
            <p class="tile-eyebrow">Estoque</p>
            <h2>Cadastro de entradas</h2>
            <p>Registrar chegada de novos lotes.</p>
        </a>

        <a class="tile" href="cadastro_saida.php">
            <p class="tile-eyebrow">Estoque</p>
            <h2>Cadastro de saídas</h2>
            <p>Perda, vencimento ou ajuste de contagem.</p>
        </a>

        <a class="tile" href="cadastro_venda.php">
            <p class="tile-eyebrow">Vendas</p>
            <h2>Registrar venda</h2>
            <p>Cria a venda e já dá baixa no estoque.</p>
        </a>

        <a class="tile" href="historico.php">
            <p class="tile-eyebrow">Estoque &amp; vendas</p>
            <h2>Histórico</h2>
            <p>Movimentações de estoque e vendas registradas.</p>
        </a>

        <a class="tile" href="cadastro_usuario.php">
            <p class="tile-eyebrow">Usuários</p>
            <h2>Cadastrar usuário</h2>
            <p>Adicionar um novo operador ao sistema.</p>
        </a>

        <?php if (usuarioEhAdmin()): ?>
            <a class="tile" href="consulta_contas.php">
                <p class="tile-eyebrow">Administração</p>
                <h2>Consulta de contas</h2>
                <p>Buscar, filtrar, promover e ativar/inativar contas.</p>
            </a>

            <a class="tile" href="cadastro_fornecedor.php">
                <p class="tile-eyebrow">Administração</p>
                <h2>Cadastrar fornecedor</h2>
                <p>Gerenciar quem fornece cada importação.</p>
            </a>
        <?php endif; ?>
    </div>
</main>

</body>
</html>
