<?php require_once __DIR__ . '/auth.php'; exigirLogin();

require_once __DIR__ . '/config/database.php';

$pdo = Database::getConnection();

$aba = ($_GET['aba'] ?? 'estoque') === 'vendas' ? 'vendas' : 'estoque';

$dataInicio = trim($_GET['data_inicio'] ?? '');
$dataFim    = trim($_GET['data_fim'] ?? '');
$busca      = trim($_GET['busca'] ?? '');

$badgesMovimentacao = [
    'entrada'          => 'badge--ok',
    'saida'            => 'badge--warn',
    'ajuste'           => 'badge--muted',
    'perda_vencimento' => 'badge--danger',
];

$badgesVenda = [
    'concluida' => 'badge--ok',
    'pendente'  => 'badge--warn',
    'cancelada' => 'badge--danger',
];

if ($aba === 'estoque') {

    $tipo = $_GET['tipo'] ?? 'todos';

    $condicoes = [];
    $parametros = [];

    if ($dataInicio !== '') {
        $condicoes[] = 'DATE(m.data_movimentacao) >= :data_inicio';
        $parametros['data_inicio'] = $dataInicio;
    }
    if ($dataFim !== '') {
        $condicoes[] = 'DATE(m.data_movimentacao) <= :data_fim';
        $parametros['data_fim'] = $dataFim;
    }
    if ($busca !== '') {
        $condicoes[] = 'p.nome LIKE :busca';
        $parametros['busca'] = '%' . $busca . '%';
    }
    if (in_array($tipo, ['entrada', 'saida', 'ajuste', 'perda_vencimento'], true)) {
        $condicoes[] = 'm.tipo = :tipo';
        $parametros['tipo'] = $tipo;
    }

    $sql = "SELECT m.id, m.tipo, m.quantidade, m.motivo, m.data_movimentacao,
                   p.nome AS produto_nome, l.numero_lote, u.nome AS usuario_nome
            FROM estoque_movimentacoes m
            JOIN produtos p ON p.id = m.produto_id
            LEFT JOIN lotes l ON l.id = m.lote_id
            LEFT JOIN usuarios u ON u.id = m.usuario_id";

    if (!empty($condicoes)) {
        $sql .= ' WHERE ' . implode(' AND ', $condicoes);
    }

    $sql .= ' ORDER BY m.data_movimentacao DESC LIMIT 200';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
    $movimentacoes = $stmt->fetchAll();

} else {

    $status = $_GET['status'] ?? 'todos';

    $condicoes = [];
    $parametros = [];

    if ($dataInicio !== '') {
        $condicoes[] = 'DATE(v.data_venda) >= :data_inicio';
        $parametros['data_inicio'] = $dataInicio;
    }
    if ($dataFim !== '') {
        $condicoes[] = 'DATE(v.data_venda) <= :data_fim';
        $parametros['data_fim'] = $dataFim;
    }
    if ($busca !== '') {
        $condicoes[] = 'c.nome LIKE :busca';
        $parametros['busca'] = '%' . $busca . '%';
    }
    if (in_array($status, ['pendente', 'concluida', 'cancelada'], true)) {
        $condicoes[] = 'v.status = :status';
        $parametros['status'] = $status;
    }

    $sql = "SELECT v.id, v.data_venda, v.valor_total, v.forma_pagamento, v.status,
                   c.nome AS cliente_nome, u.nome AS usuario_nome,
                   (SELECT COUNT(*) FROM venda_itens vi WHERE vi.venda_id = v.id) AS total_itens
            FROM vendas v
            LEFT JOIN clientes c ON c.id = v.cliente_id
            LEFT JOIN usuarios u ON u.id = v.usuario_id";

    if (!empty($condicoes)) {
        $sql .= ' WHERE ' . implode(' AND ', $condicoes);
    }

    $sql .= ' ORDER BY v.data_venda DESC LIMIT 200';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
    $vendas = $stmt->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Histórico — WSI</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>

<main class="wide">
    <h1>Histórico</h1>
    <p class="sub">Acompanhe as movimentações de estoque e as vendas registradas.</p>

    <div class="card">
        <div class="tabs">
            <a href="?aba=estoque" class="<?= $aba === 'estoque' ? 'active' : '' ?>">Movimentações de estoque</a>
            <a href="?aba=vendas" class="<?= $aba === 'vendas' ? 'active' : '' ?>">Vendas</a>
        </div>

        <form method="GET" action="" class="filter-bar">
            <input type="hidden" name="aba" value="<?= htmlspecialchars($aba) ?>">

            <div class="field">
                <label for="busca"><?= $aba === 'estoque' ? 'Produto' : 'Cliente' ?></label>
                <input type="text" id="busca" name="busca" value="<?= htmlspecialchars($busca) ?>" placeholder="Buscar por nome...">
            </div>

            <?php if ($aba === 'estoque'): ?>
                <div class="field">
                    <label for="tipo">Tipo</label>
                    <select id="tipo" name="tipo">
                        <option value="todos" <?= ($tipo ?? 'todos') === 'todos' ? 'selected' : '' ?>>Todos</option>
                        <option value="entrada" <?= ($tipo ?? '') === 'entrada' ? 'selected' : '' ?>>Entrada</option>
                        <option value="saida" <?= ($tipo ?? '') === 'saida' ? 'selected' : '' ?>>Saída</option>
                        <option value="ajuste" <?= ($tipo ?? '') === 'ajuste' ? 'selected' : '' ?>>Ajuste</option>
                        <option value="perda_vencimento" <?= ($tipo ?? '') === 'perda_vencimento' ? 'selected' : '' ?>>Perda / vencimento</option>
                    </select>
                </div>
            <?php else: ?>
                <div class="field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="todos" <?= ($status ?? 'todos') === 'todos' ? 'selected' : '' ?>>Todos</option>
                        <option value="concluida" <?= ($status ?? '') === 'concluida' ? 'selected' : '' ?>>Concluída</option>
                        <option value="pendente" <?= ($status ?? '') === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                        <option value="cancelada" <?= ($status ?? '') === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                    </select>
                </div>
            <?php endif; ?>

            <div class="field">
                <label for="data_inicio">De</label>
                <input type="date" id="data_inicio" name="data_inicio" value="<?= htmlspecialchars($dataInicio) ?>">
            </div>
            <div class="field">
                <label for="data_fim">Até</label>
                <input type="date" id="data_fim" name="data_fim" value="<?= htmlspecialchars($dataFim) ?>">
            </div>

            <div class="actions">
                <button type="submit" class="btn--sm">Filtrar</button>
                <a href="historico.php?aba=<?= htmlspecialchars($aba) ?>" class="btn btn--ghost btn--sm">Limpar</a>
            </div>
        </form>

        <div class="table-wrap">
            <?php if ($aba === 'estoque'): ?>

                <?php if (empty($movimentacoes)): ?>
                    <p class="empty-state">Nenhuma movimentação encontrada com esses filtros.</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Produto</th>
                                <th>Lote</th>
                                <th>Tipo</th>
                                <th>Qtde.</th>
                                <th>Motivo</th>
                                <th>Usuário</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movimentacoes as $m): ?>
                                <tr>
                                    <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($m['data_movimentacao']))) ?></td>
                                    <td><?= htmlspecialchars($m['produto_nome']) ?></td>
                                    <td><?= htmlspecialchars($m['numero_lote'] ?: '—') ?></td>
                                    <td>
                                        <span class="badge <?= $badgesMovimentacao[$m['tipo']] ?? 'badge--muted' ?>">
                                            <?= htmlspecialchars(str_replace('_', ' ', $m['tipo'])) ?>
                                        </span>
                                    </td>
                                    <td><?= (int)$m['quantidade'] ?></td>
                                    <td><?= htmlspecialchars($m['motivo'] ?: '—') ?></td>
                                    <td><?= htmlspecialchars($m['usuario_nome'] ?: '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

            <?php else: ?>

                <?php if (empty($vendas)): ?>
                    <p class="empty-state">Nenhuma venda encontrada com esses filtros.</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Cliente</th>
                                <th>Itens</th>
                                <th>Valor total</th>
                                <th>Pagamento</th>
                                <th>Status</th>
                                <th>Usuário</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vendas as $v): ?>
                                <tr>
                                    <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($v['data_venda']))) ?></td>
                                    <td><?= htmlspecialchars($v['cliente_nome'] ?: 'Consumidor não identificado') ?></td>
                                    <td><?= (int)$v['total_itens'] ?></td>
                                    <td>R$ <?= number_format((float)$v['valor_total'], 2, ',', '.') ?></td>
                                    <td><?= htmlspecialchars(strtoupper($v['forma_pagamento'])) ?></td>
                                    <td>
                                        <span class="badge <?= $badgesVenda[$v['status']] ?? 'badge--muted' ?>">
                                            <?= htmlspecialchars($v['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($v['usuario_nome'] ?: '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>

    <a href="home.php" class="back">← Voltar para o início</a>
</main>

</body>
</html>
