<?php require_once __DIR__ . '/auth.php'; exigirLogin(); require_once __DIR__ . '/config/database.php';

/*
 * SE-05 — Lotes próximos do vencimento.
 * Saída com dado derivado: dias para vencer (validade - hoje) e valor em risco
 * (quantidade disponível x preço de custo), além do total em risco.
 */

$pdo = Database::getConnection();

$prazosPermitidos = [7, 15, 30, 60, 90];
$prazo = (int)($_GET['prazo'] ?? 30);
if (!in_array($prazo, $prazosPermitidos, true)) {
    $prazo = 30;
}

$stmt = $pdo->prepare(
    "SELECT l.id, l.numero_lote, l.data_validade, l.quantidade_disponivel, l.preco_custo,
            p.nome AS produto_nome, f.nome AS fornecedor_nome,
            DATEDIFF(l.data_validade, CURDATE()) AS dias_para_vencer,
            (l.quantidade_disponivel * COALESCE(l.preco_custo, 0)) AS valor_em_risco
     FROM lotes l
     JOIN produtos p ON p.id = l.produto_id
     LEFT JOIN fornecedores f ON f.id = l.fornecedor_id
     WHERE p.ativo = 1
       AND l.data_validade IS NOT NULL
       AND l.quantidade_disponivel > 0
       AND DATEDIFF(l.data_validade, CURDATE()) <= :prazo
     ORDER BY l.data_validade ASC"
);
$stmt->execute(['prazo' => $prazo]);
$lotes = $stmt->fetchAll();

$totalEmRisco = 0.0;
$totalVencidos = 0;
foreach ($lotes as $l) {
    $totalEmRisco += (float)$l['valor_em_risco'];
    if ((int)$l['dias_para_vencer'] < 0) {
        $totalVencidos++;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lotes Próximos do Vencimento — WSI</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>

<main class="wide">
    <h1>Lotes próximos do vencimento</h1>
    <p class="sub">Lotes vencidos ou que vencem dentro do prazo escolhido, com o valor de custo que está em risco.</p>

    <div class="stat-grid">
        <div class="stat-card">
            <p class="stat-label">Lotes na lista</p>
            <p class="stat-value"><?= count($lotes) ?></p>
        </div>
        <div class="stat-card">
            <p class="stat-label">Já vencidos</p>
            <p class="stat-value <?= $totalVencidos > 0 ? 'stat-danger' : '' ?>"><?= $totalVencidos ?></p>
        </div>
        <div class="stat-card">
            <p class="stat-label">Valor em risco</p>
            <p class="stat-value <?= $totalEmRisco > 0 ? 'stat-warn' : '' ?>">R$ <?= number_format($totalEmRisco, 2, ',', '.') ?></p>
            <p class="stat-hint">Quantidade disponível × custo unitário</p>
        </div>
    </div>

    <div class="card">
        <form method="GET" action="" class="filter-bar">
            <div class="field">
                <label for="prazo">Vencem em até</label>
                <select id="prazo" name="prazo">
                    <?php foreach ($prazosPermitidos as $p): ?>
                        <option value="<?= $p ?>" <?= $prazo === $p ? 'selected' : '' ?>><?= $p ?> dias</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="actions">
                <button type="submit" class="btn--sm">Filtrar</button>
            </div>
        </form>

        <div class="table-wrap">
            <?php if (empty($lotes)): ?>
                <p class="empty-state">Nenhum lote vencido ou próximo do vencimento nesse prazo.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Fornecedor</th>
                            <th>Lote</th>
                            <th>Validade</th>
                            <th>Qtde. disponível</th>
                            <th>Dias para vencer</th>
                            <th>Valor em risco</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lotes as $l): ?>
                            <?php $dias = (int)$l['dias_para_vencer']; ?>
                            <tr>
                                <td><?= htmlspecialchars($l['produto_nome']) ?></td>
                                <td><?= htmlspecialchars($l['fornecedor_nome'] ?: '—') ?></td>
                                <td><?= htmlspecialchars($l['numero_lote']) ?></td>
                                <td><?= htmlspecialchars(date('d/m/Y', strtotime($l['data_validade']))) ?></td>
                                <td><?= (int)$l['quantidade_disponivel'] ?></td>
                                <td>
                                    <?php if ($dias < 0): ?>
                                        <span class="badge badge--danger">vencido há <?= abs($dias) ?> dia(s)</span>
                                    <?php elseif ($dias <= 7): ?>
                                        <span class="badge badge--warn"><?= $dias ?> dia(s)</span>
                                    <?php else: ?>
                                        <span class="badge badge--muted"><?= $dias ?> dias</span>
                                    <?php endif; ?>
                                </td>
                                <td>R$ <?= number_format((float)$l['valor_em_risco'], 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <p class="sub" style="margin-top: 16px;">
        Para tirar lotes vencidos do estoque, registre uma saída do tipo "perda / vencimento" em
        <a href="cadastro_saida.php" style="color: var(--accent);">Saídas</a>.
    </p>

    <a href="dashboard.php" class="back">← Voltar para o dashboard</a>
</main>

</body>
</html>
