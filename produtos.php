<?php require_once __DIR__ . '/auth.php'; exigirLogin(); require_once __DIR__ . '/config/database.php';

$pdo = Database::getConnection();

$categorias = [
    ''           => 'Todos',
    'roupa'      => 'Roupas',
    'cosmetico'  => 'Cosméticos',
    'brinquedo'  => 'Brinquedos',
    'jogo'       => 'Jogos',
    'filme'      => 'Filmes',
];

$categoriaAtiva = $_GET['categoria'] ?? '';

if (!array_key_exists($categoriaAtiva, $categorias)) {
    $categoriaAtiva = '';
}

$sql = '
    SELECT
        p.id, p.nome, p.descricao, p.categoria, p.codigo_barras,
        p.preco_venda, p.estoque_minimo,
        f.nome AS fornecedor_nome,

        r.tamanho, r.marca AS roupa_marca, r.sexo,

        c.categoria_cosmetico, c.tom_cor,
        c.quantidade_valor, c.quantidade_unidade,

        b.classificacao_indicativa AS classificacao_brinquedo,
        b.marca AS brinquedo_marca, b.colecao,

        j.genero AS genero_jogo,
        j.classificacao_indicativa AS classificacao_jogo,
        j.desenvolvedora, j.plataforma, j.modo_jogo,

        fi.genero AS genero_filme,
        fi.classificacao_indicativa AS classificacao_filme,
        fi.duracao_minutos, fi.data_lancamento

    FROM produtos p
    LEFT JOIN fornecedores f ON f.id = p.fornecedor_id
    LEFT JOIN roupas r      ON r.produto_id = p.id
    LEFT JOIN cosmeticos c  ON c.produto_id = p.id
    LEFT JOIN brinquedos b  ON b.produto_id = p.id
    LEFT JOIN jogos j       ON j.produto_id = p.id
    LEFT JOIN filmes fi     ON fi.produto_id = p.id
';

$params = [];

if ($categoriaAtiva !== '') {
    $sql .= ' WHERE p.categoria = :categoria';
    $params['categoria'] = $categoriaAtiva;
}

$sql .= ' ORDER BY p.nome';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Monta as linhas de meta-informação específicas de cada categoria.
 */
function metaDoProduto(array $p): array
{
    $meta = [];

    switch ($p['categoria']) {
        case 'roupa':
            if ($p['tamanho'])     $meta[] = 'Tamanho: ' . $p['tamanho'];
            if ($p['roupa_marca']) $meta[] = 'Marca: ' . $p['roupa_marca'];
            if ($p['sexo'])        $meta[] = 'Público: ' . ucfirst($p['sexo']);
            break;

        case 'cosmetico':
            if ($p['categoria_cosmetico']) $meta[] = ucfirst(str_replace('_', ' ', $p['categoria_cosmetico']));
            if ($p['quantidade_valor'])    $meta[] = 'Qtd: ' . $p['quantidade_valor'] . ' ' . $p['quantidade_unidade'];
            if ($p['tom_cor'])             $meta[] = 'Tom/cor: ' . $p['tom_cor'];
            break;

        case 'brinquedo':
            if ($p['classificacao_brinquedo']) $meta[] = 'Classificação: ' . $p['classificacao_brinquedo'];
            if ($p['brinquedo_marca'])         $meta[] = 'Marca: ' . $p['brinquedo_marca'];
            if ($p['colecao'])                 $meta[] = 'Coleção: ' . $p['colecao'];
            break;

        case 'jogo':
            if ($p['genero_jogo'])    $meta[] = 'Gênero: ' . $p['genero_jogo'];
            if ($p['plataforma'])     $meta[] = 'Plataforma: ' . $p['plataforma'];
            if ($p['modo_jogo'])      $meta[] = 'Modo: ' . str_replace('_', ' ', $p['modo_jogo']);
            break;

        case 'filme':
            if ($p['genero_filme'])       $meta[] = 'Gênero: ' . $p['genero_filme'];
            if ($p['duracao_minutos'])    $meta[] = $p['duracao_minutos'] . ' min';
            if ($p['data_lancamento'])    $meta[] = 'Lançamento: ' . date('d/m/Y', strtotime($p['data_lancamento']));
            break;
    }

    return $meta;
}

$rotulosCategoria = [
    'roupa'     => 'Roupa',
    'cosmetico' => 'Cosmético',
    'brinquedo' => 'Brinquedo',
    'jogo'      => 'Jogo',
    'filme'     => 'Filme',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Produtos — WSI</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>

<main>
    <h1>Produtos</h1>
    <p class="sub">Todos os itens cadastrados no estoque. Para ver fornecedor, lotes e validade de um item, abra "Fornecedor, lotes e validade" no card dele — ou veja a <a href="consulta_produtos.php" style="color: var(--accent);">lista por fornecedor</a>. Para cadastrar um novo produto, use <a href="cadastro_produto.php" style="color: var(--accent);">Cadastrar produto</a>.</p>

    <div class="filters">
        <?php foreach ($categorias as $valor => $rotulo): ?>
            <?php
                $href = 'produtos.php' . ($valor !== '' ? '?categoria=' . urlencode($valor) : '');
                $classeAtiva = ($categoriaAtiva === $valor) ? 'active' : '';
            ?>
            <a href="<?= htmlspecialchars($href) ?>" class="<?= $classeAtiva ?>"><?= htmlspecialchars($rotulo) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($produtos)): ?>

        <div class="empty-state">
            Nenhum produto encontrado<?= $categoriaAtiva !== '' ? ' nessa categoria' : '' ?>.
        </div>

    <?php else: ?>

        <div class="product-grid">
            <?php foreach ($produtos as $p): ?>

                <div class="product-card" style="cursor: pointer;" onclick="window.location='produto_individual.php?id=<?= (int)$p['id'] ?>'">
                    <p class="product-card__badge">
                        <?= htmlspecialchars($rotulosCategoria[$p['categoria']] ?? $p['categoria']) ?>
                    </p>

                    <h2 class="product-card__name"><?= htmlspecialchars($p['nome']) ?></h2>

                    <?php if ($p['descricao']): ?>
                        <p class="product-card__desc"><?= htmlspecialchars($p['descricao']) ?></p>
                    <?php endif; ?>

                    <?php $meta = metaDoProduto($p); ?>
                    <?php if ($p['fornecedor_nome']): ?>
                        <p class="product-card__desc" style="margin-top:2px;">Fornecedor: <?= htmlspecialchars($p['fornecedor_nome']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($meta)): ?>
                        <div class="product-card__meta">
                            <?php foreach ($meta as $linha): ?>
                                <span><?= htmlspecialchars($linha) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="product-card__footer">
                        <span class="product-card__price">
                            R$ <?= number_format((float)$p['preco_venda'], 2, ',', '.') ?>
                        </span>
                        <span class="product-card__stock">
                            Estoque mín.: <?= (int)$p['estoque_minimo'] ?>
                        </span>
                    </div>
                    <a class="btn btn--ghost btn--sm" style="margin-top:10px;" href="consulta_produtos.php?id=<?= (int)$p['id'] ?>" onclick="event.stopPropagation();">Fornecedor, lotes e validade</a>
                </div>

            <?php endforeach; ?>
        </div>

    <?php endif; ?>
</main>

</body>
</html>
