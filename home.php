<?php
require_once __DIR__ . '/auth.php';
exigirLogin();
?>
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