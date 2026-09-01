<?php require_once __DIR__ . '/auth.php'; exigirAdmin();

require_once __DIR__ . '/config/database.php';

$pdo = Database::getConnection();

$aviso = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'alternar_status') {
    $id = (int)($_POST['id'] ?? 0);

    if ($id === (int)$_SESSION['usuario_id']) {
        $erro = 'Você não pode inativar a própria conta enquanto está logado nela.';
    } elseif ($id > 0) {
        $stmt = $pdo->prepare('UPDATE usuarios SET ativo = NOT ativo WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $aviso = 'Status da conta atualizado.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'alterar_nivel') {
    $id = (int)($_POST['id'] ?? 0);
    $novoNivel = $_POST['nivel_acesso'] ?? '';

    if ($id === (int)$_SESSION['usuario_id']) {
        $erro = 'Você não pode alterar o próprio nível de acesso enquanto está logado.';
    } elseif (!in_array($novoNivel, ['admin', 'operador'], true)) {
        $erro = 'Nível de acesso inválido.';
    } elseif ($id > 0) {
        $stmt = $pdo->prepare('UPDATE usuarios SET nivel_acesso = :nivel WHERE id = :id');
        $stmt->execute(['nivel' => $novoNivel, 'id' => $id]);
        $aviso = 'Nível de acesso atualizado.';
    }
}

$busca = trim($_GET['busca'] ?? '');
$filtroStatus = $_GET['status'] ?? 'todos';
$filtroNivel = $_GET['nivel'] ?? 'todos';

$condicoes = [];
$parametros = [];

if ($busca !== '') {
    $condicoes[] = '(nome LIKE :busca OR email LIKE :busca)';
    $parametros['busca'] = '%' . $busca . '%';
}

if ($filtroStatus === 'ativos') {
    $condicoes[] = 'ativo = 1';
} elseif ($filtroStatus === 'inativos') {
    $condicoes[] = 'ativo = 0';
}

if (in_array($filtroNivel, ['admin', 'operador'], true)) {
    $condicoes[] = 'nivel_acesso = :nivel_filtro';
    $parametros['nivel_filtro'] = $filtroNivel;
}

$sql = 'SELECT id, nome, email, nivel_acesso, ativo, criado_em FROM usuarios';

if (!empty($condicoes)) {
    $sql .= ' WHERE ' . implode(' AND ', $condicoes);
}

$sql .= ' ORDER BY nome';

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$usuarios = $stmt->fetchAll();

$totalContas = $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
$totalAtivas = $pdo->query('SELECT COUNT(*) FROM usuarios WHERE ativo = 1')->fetchColumn();
$totalAdmins = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE nivel_acesso = 'admin'")->fetchColumn();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Consulta de Contas — WSI</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>

<main class="wide">
    <h1>Consulta de contas</h1>
    <p class="sub">Veja, gerencie e defina o nível de acesso das contas do sistema.</p>

    <?php if ($aviso): ?>
        <div class="msg msg--success" style="margin-bottom: 20px;"><?= htmlspecialchars($aviso) ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="msg msg--error" style="margin-bottom: 20px;"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <div class="stat-grid">
        <div class="stat-card">
            <p class="stat-label">Total de contas</p>
            <p class="stat-value"><?= (int)$totalContas ?></p>
        </div>
        <div class="stat-card">
            <p class="stat-label">Contas ativas</p>
            <p class="stat-value"><?= (int)$totalAtivas ?></p>
        </div>
        <div class="stat-card">
            <p class="stat-label">Contas inativas</p>
            <p class="stat-value"><?= (int)($totalContas - $totalAtivas) ?></p>
        </div>
        <div class="stat-card">
            <p class="stat-label">Administradores</p>
            <p class="stat-value"><?= (int)$totalAdmins ?></p>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <p class="card__eyebrow">Usuários do sistema</p>
            <h2 class="card__title">Contas cadastradas</h2>
            <p class="card__subtitle">Para criar uma nova conta, use <a href="cadastro_usuario.php" style="color: var(--accent);">Cadastrar usuário</a> (ela entra como Operador; promova aqui se precisar).</p>
        </div>

        <form method="GET" action="" class="filter-bar">
            <div class="field">
                <label for="busca">Buscar</label>
                <input type="text" id="busca" name="busca" value="<?= htmlspecialchars($busca) ?>" placeholder="Nome ou e-mail...">
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="todos" <?= $filtroStatus === 'todos' ? 'selected' : '' ?>>Todos</option>
                    <option value="ativos" <?= $filtroStatus === 'ativos' ? 'selected' : '' ?>>Somente ativas</option>
                    <option value="inativos" <?= $filtroStatus === 'inativos' ? 'selected' : '' ?>>Somente inativas</option>
                </select>
            </div>
            <div class="field">
                <label for="nivel">Nível</label>
                <select id="nivel" name="nivel">
                    <option value="todos" <?= $filtroNivel === 'todos' ? 'selected' : '' ?>>Todos</option>
                    <option value="admin" <?= $filtroNivel === 'admin' ? 'selected' : '' ?>>Administrador</option>
                    <option value="operador" <?= $filtroNivel === 'operador' ? 'selected' : '' ?>>Operador</option>
                </select>
            </div>
            <div class="actions">
                <button type="submit" class="btn--sm">Filtrar</button>
                <a href="consulta_contas.php" class="btn btn--ghost btn--sm">Limpar</a>
            </div>
        </form>

        <div class="table-wrap">
            <?php if (empty($usuarios)): ?>
                <p class="empty-state">Nenhuma conta encontrada com esses filtros.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Nível</th>
                            <th>Criada em</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                            <?php $ehVoce = (int)$u['id'] === (int)$_SESSION['usuario_id']; ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($u['nome']) ?>
                                    <?php if ($ehVoce): ?>
                                        <span class="badge badge--muted">Você</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td>
                                    <span class="badge <?= $u['nivel_acesso'] === 'admin' ? 'badge--ok' : 'badge--muted' ?>">
                                        <?= $u['nivel_acesso'] === 'admin' ? 'Administrador' : 'Operador' ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars(date('d/m/Y', strtotime($u['criado_em']))) ?></td>
                                <td>
                                    <?php if ($u['ativo']): ?>
                                        <span class="badge badge--ok">Ativa</span>
                                    <?php else: ?>
                                        <span class="badge badge--muted">Inativa</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$ehVoce): ?>
                                        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                            <form method="POST" action="" style="margin:0; display:flex; gap:6px;">
                                                <input type="hidden" name="acao" value="alterar_nivel">
                                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                                <select name="nivel_acesso" style="padding: 6px 8px; font-size: 12px;">
                                                    <option value="operador" <?= $u['nivel_acesso'] === 'operador' ? 'selected' : '' ?>>Operador</option>
                                                    <option value="admin" <?= $u['nivel_acesso'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                                                </select>
                                                <button type="submit" class="btn--ghost btn--sm">Salvar</button>
                                            </form>
                                            <form method="POST" action="" style="margin:0;">
                                                <input type="hidden" name="acao" value="alternar_status">
                                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                                <button type="submit" class="btn--sm <?= $u['ativo'] ? 'btn--danger' : 'btn--ghost' ?>">
                                                    <?= $u['ativo'] ? 'Inativar' : 'Reativar' ?>
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--ink-dim); font-size: 12px;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <a href="home.php" class="back">← Voltar para o início</a>
</main>

</body>
</html>
