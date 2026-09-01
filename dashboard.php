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

$produtosAtivos = (int)$pdo->query('SELECT COUNT(*) FROM produtos WHERE ativo = 1')->fetchColumn();

$estoqueBaixo = (int)$pdo->query(
    "SELECT COUNT(*)
     FROM produtos p
     JOIN vw_estoque_atual v ON v.produto_id = p.id
     WHERE p.ativo = 1 AND v.quantidade_total < p.estoque_minimo"
)->fetchColumn();

$produtosEstoqueBaixo = $pdo->query(
    "SELECT p.id, p.nome, p.categoria, p.estoque_minimo, v.quantidade_total,
            (p.estoque_minimo - v.quantidade_total) AS deficit
     FROM produtos p
     JOIN vw_estoque_atual v ON v.produto_id = p.id
     WHERE p.ativo = 1 AND v.quantidade_total < p.estoque_minimo
     ORDER BY deficit DESC"
)->fetchAll();

$lotesVencendo = (int)$pdo->query('SELECT COUNT(*) FROM vw_lotes_vencendo')->fetchColumn();

$vendasHoje = $pdo->query(
    "SELECT COUNT(*) AS qtd, COALESCE(SUM(valor_total), 0) AS total
     FROM vendas
     WHERE DATE(data_venda) = CURDATE() AND status = 'concluida'"
)->fetch();

$faturamentoMes = (float)$pdo->query(
    "SELECT COALESCE(SUM(valor_total), 0)
     FROM vendas
     WHERE YEAR(data_venda) = YEAR(CURDATE())
       AND MONTH(data_venda) = MONTH(CURDATE())
       AND status = 'concluida'"
)->fetchColumn();

$fornecedoresAtivos = (int)$pdo->query('SELECT COUNT(*) FROM fornecedores WHERE ativo = 1')->fetchColumn();

$categoriaStmt = $pdo->query(
    "SELECT categoria, COUNT(*) AS qtd
     FROM produtos
     WHERE ativo = 1
     GROUP BY categoria
     ORDER BY qtd DESC"
);

$rotulosCategorias = [];
$valoresCategorias = [];

foreach ($categoriaStmt as $linha) {
    $rotulosCategorias[] = $mapaCategorias[$linha['categoria']] ?? $linha['categoria'];
    $valoresCategorias[] = (int)$linha['qtd'];
}

$vendasPorDia = [];
for ($i = 6; $i >= 0; $i--) {
    $dia = date('Y-m-d', strtotime("-{$i} days"));
    $vendasPorDia[$dia] = 0.0;
}

$stmtSemana = $pdo->query(
    "SELECT DATE(data_venda) AS dia, SUM(valor_total) AS total
     FROM vendas
     WHERE data_venda >= (CURDATE() - INTERVAL 6 DAY) AND status = 'concluida'
     GROUP BY DATE(data_venda)"
);

foreach ($stmtSemana as $linha) {
    if (array_key_exists($linha['dia'], $vendasPorDia)) {
        $vendasPorDia[$linha['dia']] = (float)$linha['total'];
    }
}

$rotulosSemana = array_map(fn($d) => date('d/m', strtotime($d)), array_keys($vendasPorDia));
$valoresSemana = array_values($vendasPorDia);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — WSI</title>
<link rel="stylesheet" href="assets/css/app.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>
</head>
<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>

<main class="wide">
    <h1>Dashboard</h1>
    <p class="sub">Visão geral do estoque e das vendas.</p>

    <div class="stat-grid">
        <div class="stat-card">
            <p class="stat-label">Produtos ativos</p>
            <p class="stat-value"><?= $produtosAtivos ?></p>
        </div>
        <div class="stat-card">
            <p class="stat-label">Estoque baixo</p>
            <p class="stat-value <?= $estoqueBaixo > 0 ? 'stat-danger' : '' ?>"><?= $estoqueBaixo ?></p>
            <p class="stat-hint">Abaixo do mínimo cadastrado</p>
        </div>
        <div class="stat-card">
            <p class="stat-label">Lotes vencendo</p>
            <p class="stat-value <?= $lotesVencendo > 0 ? 'stat-warn' : '' ?>"><?= $lotesVencendo ?></p>
            <p class="stat-hint">Vencidos ou nos próximos 30 dias</p>
        </div>
        <div class="stat-card">
            <p class="stat-label">Vendas hoje</p>
            <p class="stat-value"><?= (int)$vendasHoje['qtd'] ?></p>
            <p class="stat-hint">R$ <?= number_format((float)$vendasHoje['total'], 2, ',', '.') ?></p>
        </div>
        <div class="stat-card">
            <p class="stat-label">Faturamento do mês</p>
            <p class="stat-value">R$ <?= number_format($faturamentoMes, 2, ',', '.') ?></p>
        </div>
        <div class="stat-card">
            <p class="stat-label">Fornecedores ativos</p>
            <p class="stat-value"><?= $fornecedoresAtivos ?></p>
        </div>
    </div>

    <div class="card" style="margin-bottom: 24px;">
        <div class="card__header">
            <p class="card__eyebrow">Alertas de reposição</p>
            <h2 class="card__title">Produtos com estoque abaixo do mínimo</h2>
        </div>
        <div class="table-wrap">
            <?php if (empty($produtosEstoqueBaixo)): ?>
                <p class="empty-state">Nenhum produto abaixo do estoque mínimo no momento. 🎉</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Categoria</th>
                            <th>Estoque atual</th>
                            <th>Mínimo</th>
                            <th>Faltam</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produtosEstoqueBaixo as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['nome']) ?></td>
                                <td><?= htmlspecialchars($mapaCategorias[$p['categoria']] ?? $p['categoria']) ?></td>
                                <td><?= (int)$p['quantidade_total'] ?></td>
                                <td><?= (int)$p['estoque_minimo'] ?></td>
                                <td><span class="badge badge--danger"><?= (int)$p['deficit'] ?> un.</span></td>
                                <td><a class="btn btn--ghost btn--sm" href="cadastro_entrada.php?produto_id=<?= (int)$p['id'] ?>">Repor</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="chart-grid">
        <div class="chart-card">
            <h3>Vendas concluídas — últimos 7 dias</h3>
            <canvas id="graficoVendas"></canvas>
        </div>
        <div class="chart-card">
            <h3>Produtos ativos por categoria</h3>
            <canvas id="graficoCategorias"></canvas>
        </div>
    </div>

    <a href="home.php" class="back">← Voltar para o início</a>
</main>

<script>
    const corAccent = '#4fb0a5';
    const corGrid = 'rgba(139,150,168,0.15)';
    const corTexto = '#8b96a8';

    Chart.defaults.color = corTexto;
    Chart.defaults.font.family = "'Inter', 'Segoe UI', system-ui, sans-serif";

    new Chart(document.getElementById('graficoVendas'), {
        type: 'line',
        data: {
            labels: <?= json_encode($rotulosSemana) ?>,
            datasets: [{
                label: 'Vendas (R$)',
                data: <?= json_encode($valoresSemana) ?>,
                borderColor: corAccent,
                backgroundColor: 'rgba(79,176,165,0.15)',
                tension: 0.3,
                fill: true,
                pointRadius: 3,
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: corGrid } },
                y: { grid: { color: corGrid }, beginAtZero: true }
            }
        }
    });

    new Chart(document.getElementById('graficoCategorias'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($rotulosCategorias) ?>,
            datasets: [{
                label: 'Produtos',
                data: <?= json_encode($valoresCategorias) ?>,
                backgroundColor: corAccent,
                borderRadius: 4,
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false } },
                y: { grid: { color: corGrid }, beginAtZero: true, ticks: { stepSize: 1 } }
            }
        }
    });
</script>

</body>
</html>