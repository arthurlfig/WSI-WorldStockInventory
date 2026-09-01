<?php require_once __DIR__ . '/auth.php'; exigirLogin(); require_once __DIR__ . '/config/database.php';

$pdo = Database::getConnection();

$erros = [];
$sucesso = '';

$valores = [
    'produto_id' => '',
    'fornecedor_id' => '',
    'numero_lote' => '',
    'data_fabricacao' => '',
    'data_validade' => '',
    'quantidade_recebida' => '',
    'preco_custo' => '',
    'nota_fiscal' => '',
];

$produtos = $pdo->query(
    "SELECT id, nome, categoria, fornecedor_id
     FROM produtos
     WHERE ativo = 1
     ORDER BY nome"
)->fetchAll();

$fornecedores = $pdo->query(
    "SELECT id, nome
     FROM fornecedores
     WHERE ativo = 1
     ORDER BY nome"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['produto_id']) && ctype_digit((string)$_GET['produto_id'])) {
    $valores['produto_id'] = $_GET['produto_id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($valores as $campo => $valor) {
        if (isset($_POST[$campo])) {
            $valores[$campo] = trim($_POST[$campo]);
        }
    }

    if ($valores['produto_id'] === '' || !ctype_digit($valores['produto_id'])) {
        $erros[] = 'Selecione o produto que está entrando no estoque.';
    }

    if ($valores['numero_lote'] === '') {
        $erros[] = 'Informe o número do lote.';
    }

    if (
        $valores['quantidade_recebida'] === '' ||
        !ctype_digit($valores['quantidade_recebida']) ||
        (int)$valores['quantidade_recebida'] <= 0
    ) {
        $erros[] = 'Informe uma quantidade recebida válida (número inteiro maior que zero).';
    }

    if (
        $valores['preco_custo'] !== '' &&
        (!is_numeric($valores['preco_custo']) || $valores['preco_custo'] < 0)
    ) {
        $erros[] = 'Informe um preço de custo válido.';
    }

    if (
        $valores['data_fabricacao'] !== '' && $valores['data_validade'] !== '' &&
        strtotime($valores['data_validade']) < strtotime($valores['data_fabricacao'])
    ) {
        $erros[] = 'A data de validade não pode ser anterior à data de fabricação.';
    }

    if ($valores['fornecedor_id'] !== '' && !ctype_digit($valores['fornecedor_id'])) {
        $erros[] = 'Fornecedor inválido.';
    }

    if (empty($erros)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO lotes
                    (produto_id, fornecedor_id, numero_lote, data_fabricacao, data_validade,
                     quantidade_recebida, quantidade_disponivel, preco_custo, nota_fiscal)
                 VALUES
                    (:produto_id, :fornecedor_id, :numero_lote, :data_fabricacao, :data_validade,
                     :quantidade_recebida, :quantidade_disponivel, :preco_custo, :nota_fiscal)'
            );

            $stmt->execute([
                'produto_id' => $valores['produto_id'],
                'fornecedor_id' => $valores['fornecedor_id'] !== '' ? $valores['fornecedor_id'] : null,
                'numero_lote' => $valores['numero_lote'],
                'data_fabricacao' => $valores['data_fabricacao'] !== '' ? $valores['data_fabricacao'] : null,
                'data_validade' => $valores['data_validade'] !== '' ? $valores['data_validade'] : null,
                'quantidade_recebida' => $valores['quantidade_recebida'],
                'quantidade_disponivel' => $valores['quantidade_recebida'],
                'preco_custo' => $valores['preco_custo'] !== '' ? $valores['preco_custo'] : null,
                'nota_fiscal' => $valores['nota_fiscal'] !== '' ? $valores['nota_fiscal'] : null,
            ]);

            $loteId = $pdo->lastInsertId();

            $stmt = $pdo->prepare(
                'INSERT INTO estoque_movimentacoes
                    (produto_id, lote_id, tipo, quantidade, motivo, usuario_id)
                 VALUES
                    (:produto_id, :lote_id, \'entrada\', :quantidade, :motivo, :usuario_id)'
            );

            $stmt->execute([
                'produto_id' => $valores['produto_id'],
                'lote_id' => $loteId,
                'quantidade' => $valores['quantidade_recebida'],
                'motivo' => 'Entrada de lote ' . $valores['numero_lote'],
                'usuario_id' => $_SESSION['usuario_id'],
            ]);

            $pdo->commit();

            $sucesso = 'Entrada registrada com sucesso! Lote #' . $loteId . ' criado.';

            foreach ($valores as $campo => $valor) {
                $valores[$campo] = '';
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erros[] = 'Não foi possível registrar a entrada.';
        }
    }
}

$ultimasEntradas = $pdo->query(
    "SELECT l.id, l.numero_lote, l.data_validade, l.quantidade_recebida, l.preco_custo, l.criado_em,
            p.nome AS produto_nome, f.nome AS fornecedor_nome
     FROM lotes l
     JOIN produtos p ON p.id = l.produto_id
     LEFT JOIN fornecedores f ON f.id = l.fornecedor_id
     ORDER BY l.criado_em DESC
     LIMIT 15"
)->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cadastro de Entradas — WSI</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>

<main class="wide">
    <h1>Cadastro de entradas</h1>
    <p class="sub">Registre a chegada de um novo lote no estoque (importação, reposição etc).</p>

    <div class="card" style="margin-bottom: 24px;">
        <div class="card__header">
            <p class="card__eyebrow">Estoque · Nova entrada</p>
            <h2 class="card__title">Registrar entrada de lote</h2>
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

            <?php if ($sucesso): ?>
                <div class="msg msg--success"><?= htmlspecialchars($sucesso) ?></div>
            <?php endif; ?>

            <?php if (empty($produtos)): ?>
                <div class="msg msg--error">
                    Nenhum produto ativo cadastrado ainda. <a href="cadastro_produto.php" style="color: inherit; text-decoration: underline;">Cadastre um produto</a> antes de registrar entradas.
                </div>
            <?php endif; ?>

            <div class="field-grid">
                <div class="field-full">
                    <label for="produto_id">Produto</label>
                    <select id="produto_id" name="produto_id" required <?= empty($produtos) ? 'disabled' : '' ?>>
                        <option value="">Selecione...</option>
                        <?php foreach ($produtos as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" <?= (string)$valores['produto_id'] === (string)$p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['nome']) ?> (<?= htmlspecialchars($p['categoria']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="fornecedor_id">Fornecedor do lote</label>
                    <select id="fornecedor_id" name="fornecedor_id">
                        <option value="">Nenhum fornecedor</option>
                        <?php foreach ($fornecedores as $f): ?>
                            <option value="<?= (int)$f['id'] ?>" <?= (string)$valores['fornecedor_id'] === (string)$f['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($f['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="numero_lote">Número do lote</label>
                    <input type="text" id="numero_lote" name="numero_lote" value="<?= htmlspecialchars($valores['numero_lote']) ?>" maxlength="60" required>
                </div>

                <div>
                    <label for="data_fabricacao">Data de fabricação</label>
                    <input type="date" id="data_fabricacao" name="data_fabricacao" value="<?= htmlspecialchars($valores['data_fabricacao']) ?>">
                </div>

                <div>
                    <label for="data_validade">Data de validade</label>
                    <input type="date" id="data_validade" name="data_validade" value="<?= htmlspecialchars($valores['data_validade']) ?>">
                </div>

                <div>
                    <label for="quantidade_recebida">Quantidade recebida</label>
                    <input type="number" id="quantidade_recebida" name="quantidade_recebida" value="<?= htmlspecialchars($valores['quantidade_recebida']) ?>" min="1" step="1" required>
                </div>

                <div>
                    <label for="preco_custo">Preço de custo (por unidade)</label>
                    <input type="number" id="preco_custo" name="preco_custo" value="<?= htmlspecialchars($valores['preco_custo']) ?>" min="0" step="0.01" placeholder="0,00">
                </div>

                <div>
                    <label for="nota_fiscal">Nota fiscal</label>
                    <input type="text" id="nota_fiscal" name="nota_fiscal" value="<?= htmlspecialchars($valores['nota_fiscal']) ?>" maxlength="60">
                </div>
            </div>

            <button type="submit" <?= empty($produtos) ? 'disabled' : '' ?>>Registrar entrada</button>
        </form>
    </div>

    <div class="card">
        <div class="card__header">
            <p class="card__eyebrow">Conferência</p>
            <h2 class="card__title">Últimas entradas registradas</h2>
        </div>
        <div class="table-wrap">
            <?php if (empty($ultimasEntradas)): ?>
                <p class="empty-state">Nenhuma entrada registrada ainda.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Lote</th>
                            <th>Produto</th>
                            <th>Fornecedor</th>
                            <th>Qtde.</th>
                            <th>Custo unit.</th>
                            <th>Validade</th>
                            <th>Registrado em</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimasEntradas as $e): ?>
                            <tr>
                                <td><?= htmlspecialchars($e['numero_lote']) ?></td>
                                <td><?= htmlspecialchars($e['produto_nome']) ?></td>
                                <td><?= htmlspecialchars($e['fornecedor_nome'] ?: '—') ?></td>
                                <td><?= (int)$e['quantidade_recebida'] ?></td>
                                <td><?= $e['preco_custo'] !== null ? 'R$ ' . number_format((float)$e['preco_custo'], 2, ',', '.') : '—' ?></td>
                                <td><?= $e['data_validade'] ? htmlspecialchars(date('d/m/Y', strtotime($e['data_validade']))) : '—' ?></td>
                                <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($e['criado_em']))) ?></td>
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