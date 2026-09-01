<?php require_once __DIR__ . '/auth.php'; exigirAdmin(); require_once __DIR__ . '/config/database.php';

$pdo = Database::getConnection();

$erros = [];
$sucesso = '';

$valores = [
    'id' => '',
    'nome' => '',
    'cnpj_cpf' => '',
    'pais_origem' => '',
    'telefone' => '',
    'email' => '',
    'endereco' => '',
    'contato_responsavel' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'alternar_status') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE fornecedores SET ativo = NOT ativo WHERE id = :id');
            $stmt->execute(['id' => $id]);
        }

        header('Location: cadastro_fornecedor.php');
        exit;
    }

    if ($acao === 'salvar') {
        foreach ($valores as $campo => $valor) {
            if (isset($_POST[$campo])) {
                $valores[$campo] = trim($_POST[$campo]);
            }
        }

        if ($valores['nome'] === '') {
            $erros[] = 'Informe o nome do fornecedor.';
        }

        if ($valores['email'] !== '' && !filter_var($valores['email'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'Informe um e-mail válido (ou deixe em branco).';
        }

        if (empty($erros)) {
            try {
                if ($valores['id'] !== '') {
                    // Atualização
                    $stmt = $pdo->prepare(
                        'UPDATE fornecedores SET
                            nome = :nome,
                            cnpj_cpf = :cnpj_cpf,
                            pais_origem = :pais_origem,
                            telefone = :telefone,
                            email = :email,
                            endereco = :endereco,
                            contato_responsavel = :contato_responsavel
                         WHERE id = :id'
                    );

                    $stmt->execute([
                        'nome' => $valores['nome'],
                        'cnpj_cpf' => $valores['cnpj_cpf'] !== '' ? $valores['cnpj_cpf'] : null,
                        'pais_origem' => $valores['pais_origem'] !== '' ? $valores['pais_origem'] : null,
                        'telefone' => $valores['telefone'] !== '' ? $valores['telefone'] : null,
                        'email' => $valores['email'] !== '' ? $valores['email'] : null,
                        'endereco' => $valores['endereco'] !== '' ? $valores['endereco'] : null,
                        'contato_responsavel' => $valores['contato_responsavel'] !== '' ? $valores['contato_responsavel'] : null,
                        'id' => $valores['id'],
                    ]);

                    $sucesso = 'Fornecedor atualizado com sucesso!';
                } else {
                    // Inserção
                    $stmt = $pdo->prepare(
                        'INSERT INTO fornecedores
                            (nome, cnpj_cpf, pais_origem, telefone, email, endereco, contato_responsavel)
                         VALUES
                            (:nome, :cnpj_cpf, :pais_origem, :telefone, :email, :endereco, :contato_responsavel)'
                    );

                    $stmt->execute([
                        'nome' => $valores['nome'],
                        'cnpj_cpf' => $valores['cnpj_cpf'] !== '' ? $valores['cnpj_cpf'] : null,
                        'pais_origem' => $valores['pais_origem'] !== '' ? $valores['pais_origem'] : null,
                        'telefone' => $valores['telefone'] !== '' ? $valores['telefone'] : null,
                        'email' => $valores['email'] !== '' ? $valores['email'] : null,
                        'endereco' => $valores['endereco'] !== '' ? $valores['endereco'] : null,
                        'contato_responsavel' => $valores['contato_responsavel'] !== '' ? $valores['contato_responsavel'] : null,
                    ]);

                    $sucesso = 'Fornecedor cadastrado com sucesso!';
                }

                foreach ($valores as $campo => $valor) {
                    $valores[$campo] = '';
                }
            } catch (PDOException $e) {
                $erros[] = 'Não foi possível salvar o fornecedor.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT * FROM fornecedores WHERE id = :id');
    $stmt->execute(['id' => (int)$_GET['editar']]);
    $fornecedorEditando = $stmt->fetch();

    if ($fornecedorEditando) {
        foreach ($valores as $campo => $valor) {
            if (array_key_exists($campo, $fornecedorEditando)) {
                $valores[$campo] = $fornecedorEditando[$campo];
            }
        }
        $valores['id'] = $fornecedorEditando['id'];
    }
}

$busca = trim($_GET['busca'] ?? '');
$filtroStatus = $_GET['status'] ?? 'todos';

$condicoes = [];
$parametros = [];

if ($busca !== '') {
    $condicoes[] = '(nome LIKE :busca OR pais_origem LIKE :busca OR contato_responsavel LIKE :busca)';
    $parametros['busca'] = '%' . $busca . '%';
}

if ($filtroStatus === 'ativos') {
    $condicoes[] = 'ativo = 1';
} elseif ($filtroStatus === 'inativos') {
    $condicoes[] = 'ativo = 0';
}

$sql = 'SELECT id, nome, cnpj_cpf, pais_origem, telefone, email, contato_responsavel, ativo, criado_em
        FROM fornecedores';

if (!empty($condicoes)) {
    $sql .= ' WHERE ' . implode(' AND ', $condicoes);
}

$sql .= ' ORDER BY nome';

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$fornecedores = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fornecedores — WSI</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>

<main class="wide">
    <h1>Fornecedores</h1>
    <p class="sub">Cadastre e gerencie os fornecedores usados nas importações.</p>

    <?php if ($sucesso): ?>
        <div class="msg msg--success" style="margin-bottom: 20px;"><?= htmlspecialchars($sucesso) ?></div>
    <?php endif; ?>

    <div class="card" style="margin-bottom: 24px;">
        <div class="card__header">
            <p class="card__eyebrow">
                Fornecedores · <?= $valores['id'] !== '' ? 'Editar registro' : 'Novo registro' ?>
            </p>
            <h2 class="card__title"><?= $valores['id'] !== '' ? 'Editar fornecedor' : 'Cadastro de fornecedor' ?></h2>
        </div>

        <form method="POST" action="" class="stack" novalidate>
            <?php if (!empty($erros)): ?>
                <div class="msg msg--error">
                    <strong>Corrija os itens abaixo:</strong>
                    <ul>
                        <?php foreach ($erros as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <input type="hidden" name="acao" value="salvar">
            <input type="hidden" name="id" value="<?= htmlspecialchars($valores['id']) ?>">

            <div class="field-grid">
                <div class="field-full">
                    <label for="nome">Nome do fornecedor</label>
                    <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($valores['nome']) ?>" maxlength="150" required>
                </div>

                <div>
                    <label for="cnpj_cpf">CNPJ / CPF</label>
                    <input type="text" id="cnpj_cpf" name="cnpj_cpf" value="<?= htmlspecialchars($valores['cnpj_cpf']) ?>" maxlength="20">
                </div>

                <div>
                    <label for="pais_origem">País de origem</label>
                    <input type="text" id="pais_origem" name="pais_origem" value="<?= htmlspecialchars($valores['pais_origem']) ?>" placeholder="Coreia do Sul, EUA, França..." maxlength="80">
                </div>

                <div>
                    <label for="telefone">Telefone</label>
                    <input type="text" id="telefone" name="telefone" value="<?= htmlspecialchars($valores['telefone']) ?>" maxlength="30">
                </div>

                <div>
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($valores['email']) ?>" maxlength="150">
                </div>

                <div class="field-full">
                    <label for="endereco">Endereço</label>
                    <input type="text" id="endereco" name="endereco" value="<?= htmlspecialchars($valores['endereco']) ?>" maxlength="255">
                </div>

                <div class="field-full">
                    <label for="contato_responsavel">Contato responsável</label>
                    <input type="text" id="contato_responsavel" name="contato_responsavel" value="<?= htmlspecialchars($valores['contato_responsavel']) ?>" maxlength="120">
                </div>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit"><?= $valores['id'] !== '' ? 'Salvar alterações' : 'Cadastrar fornecedor' ?></button>
                <?php if ($valores['id'] !== ''): ?>
                    <a href="cadastro_fornecedor.php" class="btn btn--ghost">Cancelar edição</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card__header">
            <p class="card__eyebrow">Fornecedores cadastrados</p>
            <h2 class="card__title">Lista de fornecedores</h2>
        </div>

        <form method="GET" action="" class="filter-bar">
            <div class="field">
                <label for="busca">Buscar</label>
                <input type="text" id="busca" name="busca" value="<?= htmlspecialchars($busca) ?>" placeholder="Nome, país, contato...">
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="todos" <?= $filtroStatus === 'todos' ? 'selected' : '' ?>>Todos</option>
                    <option value="ativos" <?= $filtroStatus === 'ativos' ? 'selected' : '' ?>>Somente ativos</option>
                    <option value="inativos" <?= $filtroStatus === 'inativos' ? 'selected' : '' ?>>Somente inativos</option>
                </select>
            </div>
            <div class="actions">
                <button type="submit" class="btn--sm">Filtrar</button>
                <a href="cadastro_fornecedor.php" class="btn btn--ghost btn--sm">Limpar</a>
            </div>
        </form>

        <div class="table-wrap">
            <?php if (empty($fornecedores)): ?>
                <p class="empty-state">Nenhum fornecedor encontrado com esses filtros.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>País</th>
                            <th>Contato</th>
                            <th>Telefone / E-mail</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fornecedores as $f): ?>
                            <tr>
                                <td><?= htmlspecialchars($f['nome']) ?></td>
                                <td><?= htmlspecialchars($f['pais_origem'] ?: '—') ?></td>
                                <td><?= htmlspecialchars($f['contato_responsavel'] ?: '—') ?></td>
                                <td>
                                    <?= htmlspecialchars($f['telefone'] ?: '—') ?><br>
                                    <span style="color: var(--ink-dim);"><?= htmlspecialchars($f['email'] ?: '') ?></span>
                                </td>
                                <td>
                                    <?php if ($f['ativo']): ?>
                                        <span class="badge badge--ok">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge badge--muted">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                        <a class="btn btn--ghost btn--sm" href="cadastro_fornecedor.php?editar=<?= (int)$f['id'] ?>">Editar</a>
                                        <form method="POST" action="" style="margin:0;">
                                            <input type="hidden" name="acao" value="alternar_status">
                                            <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                                            <button type="submit" class="btn--sm <?= $f['ativo'] ? 'btn--danger' : 'btn--ghost' ?>">
                                                <?= $f['ativo'] ? 'Inativar' : 'Reativar' ?>
                                            </button>
                                        </form>
                                    </div>
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
