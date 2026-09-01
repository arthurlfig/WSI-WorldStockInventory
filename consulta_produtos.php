<?php require_once __DIR__ . '/auth.php'; exigirLogin();

require_once __DIR__ . '/config/database.php';

$pdo = Database::getConnection();

$mapaCategorias = [
    'roupa' => 'Roupas',
    'cosmetico' => 'Cosméticos',
    'brinquedo' => 'Brinquedos',
    'jogo' => 'Jogos',
    'filme' => 'Filmes',
];

$idDetalhe = isset($_GET['id']) && ctype_digit($_GET['id']) ? (int)$_GET['id'] : null;

if ($idDetalhe !== null) {
    $stmt = $pdo->prepare(
        "SELECT p.*, f.nome AS fornecedor_nome, f.pais_origem, f.telefone AS fornecedor_telefone,
                f.email AS fornecedor_email, f.contato_responsavel
         FROM produtos p
         LEFT JOIN fornecedores f ON f.id = p.fornecedor_id
         WHERE p.id = :id"
    );
    $stmt->execute(['id' => $idDetalhe]);
    $produto = $stmt->fetch();

    if (!$produto) {
        header('Location: consulta_produtos.php');
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT l.*, DATEDIFF(l.data_validade, CURDATE()) AS dias_para_vencer
         FROM lotes l
         WHERE l.produto_id = :produto_id
         ORDER BY (l.data_validade IS NULL), l.data_validade, l.criado_em DESC"
    );
    $stmt->execute(['produto_id' => $idDetalhe]);
    $lotes = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT COALESCE(SUM(quantidade_disponivel), 0) FROM lotes WHERE produto_id = :id');
    $stmt->execute(['id' => $idDetalhe]);
    $estoqueAtual = (int)$stmt->fetchColumn();
}

if ($idDetalhe === null) {
    $busca = trim($_GET['busca'] ?? '');
    $filtroCategoria = $_GET['categoria'] ?? 'todas';

    $condicoes = ['p.ativo = 1'];
    $parametros = [];

    if ($busca !== '') {
        $condicoes[] = '(p.nome LIKE :busca OR f.nome LIKE :busca OR f.pais_origem LIKE :busca)';
        $parametros['busca'] = '%' . $busca . '%';
    }

    if (in_array($filtroCategoria, array_keys($mapaCategorias), true)) {
        $condicoes[] = 'p.categoria = :categoria';
        $parametros['categoria'] = $filtroCategoria;
    }

    $sql = "SELECT p.id, p.nome, p.categoria, p.estoque_minimo, p.preco_venda,
                   f.nome AS fornecedor_nome, f.pais_origem,
                   COALESCE(v.quantidade_total, 0) AS quantidade_total
            FROM produtos p
            LEFT JOIN fornecedores f ON f.id = p.fornecedor_id
            LEFT JOIN vw_estoque_atual v ON v.produto_id = p.id
            WHERE " . implode(' AND ', $condicoes) . "
            ORDER BY p.nome";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
    $produtos = $stmt->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Consulta de Produtos — WSI</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>

<main class="wide">

<?php if ($idDetalhe !== null): ?>

    <h1><?= htmlspecialchars($produto['nome']) ?></h1>
    <p class="sub"><?= htmlspecialchars($mapaCategorias[$produto['categoria']] ?? $produto['categoria']) ?> · Estoque atual: <?= $estoqueAtual ?> un.</p>

    <div class="stat-grid">
        <div class="stat-card">
            <p class="stat-label">Estoque atual</p>
            <p class="stat-value <?= $estoqueAtual < (int)$produto['estoque_minimo'] ? 'stat-danger' : '' ?>"><?= $estoqueAtual ?></p>
            <p class="stat-hint">Mínimo cadastrado: <?= (int)$produto['estoque_minimo'] ?></p>
        </div>
        <div class="stat-card">
            <p class="stat-label">Preço de venda</p>
            <p class="stat-value">R$ <?= number_format((float)$produto['preco_venda'], 2, ',', '.') ?></p>
        </div>
        <div class="stat-card">
            <p class="stat-label">Lotes cadastrados</p>
            <p class="stat-value"><?= count($lotes) ?></p>
        </div>
    </div>

    <div class="card" style="margin-bottom: 24px;">
        <div class="card__header">
            <p class="card__eyebrow">Origem / Fornecedor</p>
            <h2 class="card__title"><?= htmlspecialchars($produto['fornecedor_nome'] ?? 'Sem fornecedor cadastrado') ?></h2>
        </div>
        <div class="card__body">
            <?php if ($produto['fornecedor_nome']): ?>
                <div class="field-grid">
                    <div>
                        <label>País de origem</label>
                        <p style="margin:0;"><?= htmlspecialchars($produto['pais_origem'] ?: '—') ?></p>
                    </div>
                    <div>
                        <label>Contato responsável</label>
                        <p style="margin:0;"><?= htmlspecialchars($produto['contato_responsavel'] ?: '—') ?></p>
                    </div>
                    <div>
                        <label>Telefone</label>
                        <p style="margin:0;"><?= htmlspecialchars($produto['fornecedor_telefone'] ?: '—') ?></p>
                    </div>
                    <div>
                        <label>E-mail</label>
                        <p style="margin:0;"><?= htmlspecialchars($produto['fornecedor_email'] ?: '—') ?></p>
                    </div>
                </div>
            <?php else: ?>
                <p class="empty-state">Este produto não tem fornecedor vinculado.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <p class="card__eyebrow">Rastreabilidade</p>
            <h2 class="card__title">Lotes deste produto</h2>
        </div>
        <div class="table-wrap">
            <?php if (empty($lotes)): ?>
                <p class="empty-state">Nenhum lote registrado ainda para este produto. Use <a href="cadastro_entrada.php?produto_id=<?= (int)$produto['id'] ?>" style="color: var(--accent);">Cadastro de entradas</a>.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Lote</th>
                            <th>Fabricação</th>
                            <th>Validade</th>
                            <th>Recebido</th>
                            <th>Disponível</th>
                            <th>Custo unit.</th>
                            <th>Nota fiscal</th>
                            <th>Situação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lotes as $l): ?>
                            <tr>
                                <td><?= htmlspecialchars($l['numero_lote']) ?></td>
                                <td><?= $l['data_fabricacao'] ? htmlspecialchars(date('d/m/Y', strtotime($l['data_fabricacao']))) : '—' ?></td>
                                <td><?= $l['data_validade'] ? htmlspecialchars(date('d/m/Y', strtotime($l['data_validade']))) : '—' ?></td>
                                <td><?= (int)$l['quantidade_recebida'] ?></td>
                                <td><?= (int)$l['quantidade_disponivel'] ?></td>
                                <td><?= $l['preco_custo'] !== null ? 'R$ ' . number_format((float)$l['preco_custo'], 2, ',', '.') : '—' ?></td>
                                <td><?= htmlspecialchars($l['nota_fiscal'] ?: '—') ?></td>
                                <td>
                                    <?php if ((int)$l['quantidade_disponivel'] === 0): ?>
                                        <span class="badge badge--muted">Esgotado</span>
                                    <?php elseif ($l['dias_para_vencer'] !== null && $l['dias_para_vencer'] < 0): ?>
                                        <span class="badge badge--danger">Vencido</span>
                                    <?php elseif ($l['dias_para_vencer'] !== null && $l['dias_para_vencer'] <= 30): ?>
                                        <span class="badge badge--warn">Vence em <?= (int)$l['dias_para_vencer'] ?>d</span>
                                    <?php else: ?>
                                        <span class="badge badge--ok">Ok</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <a href="consulta_produtos.php" class="back">← Voltar para a lista de produtos</a>

<?php else: ?>

    <h1>Consulta de produtos</h1>
    <p class="sub">Veja fornecedor, origem, lotes e validade de cada produto importado.</p>

    <div class="card">
        <div class="card__header">
            <p class="card__eyebrow">Catálogo</p>
            <h2 class="card__title">Produtos ativos</h2>
            <p class="card__subtitle">Para cadastrar um novo, use <a href="cadastro_produto.php" style="color: var(--accent);">Cadastrar produto</a>.</p>
        </div>

        <form method="GET" action="" class="filter-bar">
            <div class="field">
                <label for="busca">Buscar</label>
                <input type="text" id="busca" name="busca" value="<?= htmlspecialchars($busca) ?>" placeholder="Produto, fornecedor ou país...">
            </div>
            <div class="field">
                <label for="categoria">Categoria</label>
                <select id="categoria" name="categoria">
                    <option value="todas" <?= $filtroCategoria === 'todas' ? 'selected' : '' ?>>Todas</option>
                    <?php foreach ($mapaCategorias as $chave => $rotulo): ?>
                        <option value="<?= $chave ?>" <?= $filtroCategoria === $chave ? 'selected' : '' ?>><?= $rotulo ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="actions">
                <button type="submit" class="btn--sm">Filtrar</button>
                <a href="consulta_produtos.php" class="btn btn--ghost btn--sm">Limpar</a>
            </div>
        </form>

        <div class="table-wrap">
            <?php if (empty($produtos)): ?>
                <p class="empty-state">Nenhum produto encontrado com esses filtros.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Categoria</th>
                            <th>Fornecedor</th>
                            <th>Origem</th>
                            <th>Estoque</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produtos as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['nome']) ?></td>
                                <td><?= htmlspecialchars($mapaCategorias[$p['categoria']] ?? $p['categoria']) ?></td>
                                <td><?= htmlspecialchars($p['fornecedor_nome'] ?: '—') ?></td>
                                <td><?= htmlspecialchars($p['pais_origem'] ?: '—') ?></td>
                                <td>
                                    <?= (int)$p['quantidade_total'] ?>
                                    <?php if ((int)$p['quantidade_total'] < (int)$p['estoque_minimo']): ?>
                                        <span class="badge badge--danger">baixo</span>
                                    <?php endif; ?>
                                </td>
                                <td><a class="btn btn--ghost btn--sm" href="consulta_produtos.php?id=<?= (int)$p['id'] ?>">Ver lotes</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <a href="home.php" class="back">← Voltar para o início</a>

<?php endif; ?>

</main>

</body>
</html>
