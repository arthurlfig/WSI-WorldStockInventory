<?php require_once __DIR__ . '/auth.php'; exigirLogin(); require_once __DIR__ . '/config/database.php';

/*
 * EE-25 — Alterar lote.
 * Altera os dados cadastrais do lote. Quantidade não é editada aqui:
 * ela só muda por entrada, saída, venda ou estorno, para manter o histórico coerente.
 */

$pdo = Database::getConnection();

$erros = [];
$sucesso = '';

$loteId = $_GET['id'] ?? $_POST['id'] ?? '';
if ($loteId === '' || !ctype_digit((string)$loteId)) {
    header('Location: cadastro_entrada.php');
    exit;
}

function carregarLote(PDO $pdo, string $id)
{
    $stmt = $pdo->prepare(
        'SELECT l.*, p.nome AS produto_nome
         FROM lotes l
         JOIN produtos p ON p.id = l.produto_id
         WHERE l.id = :id'
    );
    $stmt->execute(['id' => $id]);
    return $stmt->fetch();
}

$lote = carregarLote($pdo, (string)$loteId);
if (!$lote) {
    header('Location: cadastro_entrada.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT id, nome FROM fornecedores WHERE ativo = 1 OR id = :atual ORDER BY nome'
);
$stmt->execute(['atual' => $lote['fornecedor_id'] ?? 0]);
$fornecedores = $stmt->fetchAll();

$valores = [
    'numero_lote'     => (string)$lote['numero_lote'],
    'data_fabricacao' => (string)($lote['data_fabricacao'] ?? ''),
    'data_validade'   => (string)($lote['data_validade'] ?? ''),
    'preco_custo'     => $lote['preco_custo'] !== null ? (string)$lote['preco_custo'] : '',
    'nota_fiscal'     => (string)($lote['nota_fiscal'] ?? ''),
    'fornecedor_id'   => $lote['fornecedor_id'] !== null ? (string)$lote['fornecedor_id'] : '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($valores as $campo => $valor) {
        if (isset($_POST[$campo])) {
            $valores[$campo] = trim($_POST[$campo]);
        }
    }

    if ($valores['numero_lote'] === '') {
        $erros[] = 'Informe o número do lote.';
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

    if ($valores['fornecedor_id'] !== '') {
        if (!ctype_digit($valores['fornecedor_id'])) {
            $erros[] = 'Fornecedor inválido.';
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM fornecedores WHERE id = :id');
            $stmt->execute(['id' => $valores['fornecedor_id']]);
            if ((int)$stmt->fetchColumn() === 0) {
                $erros[] = 'Fornecedor não encontrado.';
            }
        }
    }

    if (empty($erros)) {
        try {
            $stmt = $pdo->prepare(
                'UPDATE lotes
                 SET fornecedor_id = :fornecedor_id,
                     numero_lote = :numero_lote,
                     data_fabricacao = :data_fabricacao,
                     data_validade = :data_validade,
                     preco_custo = :preco_custo,
                     nota_fiscal = :nota_fiscal
                 WHERE id = :id'
            );
            $stmt->execute([
                'fornecedor_id'   => $valores['fornecedor_id'] !== '' ? $valores['fornecedor_id'] : null,
                'numero_lote'     => $valores['numero_lote'],
                'data_fabricacao' => $valores['data_fabricacao'] !== '' ? $valores['data_fabricacao'] : null,
                'data_validade'   => $valores['data_validade'] !== '' ? $valores['data_validade'] : null,
                'preco_custo'     => $valores['preco_custo'] !== '' ? $valores['preco_custo'] : null,
                'nota_fiscal'     => $valores['nota_fiscal'] !== '' ? $valores['nota_fiscal'] : null,
                'id'              => $lote['id'],
            ]);

            $sucesso = 'Lote #' . (int)$lote['id'] . ' atualizado com sucesso!';
            $lote = carregarLote($pdo, (string)$lote['id']);
        } catch (PDOException $e) {
            $erros[] = 'Não foi possível atualizar o lote.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Alterar Lote — WSI</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>

<main class="wide">
    <h1>Alterar lote</h1>
    <p class="sub">Corrija os dados cadastrais do lote. Para mudar o saldo, use entradas, saídas ou estornos.</p>

    <div class="card" style="margin-bottom: 24px;">
        <div class="card__header">
            <p class="card__eyebrow">Estoque · Lote #<?= (int)$lote['id'] ?></p>
            <h2 class="card__title"><?= htmlspecialchars($lote['produto_nome']) ?></h2>
            <p class="card__subtitle">
                Recebido: <?= (int)$lote['quantidade_recebida'] ?> un. ·
                Disponível: <?= (int)$lote['quantidade_disponivel'] ?> un.
            </p>
        </div>

        <form method="POST" action="" class="stack" novalidate>
            <input type="hidden" name="id" value="<?= (int)$lote['id'] ?>">

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

            <div class="field-grid">
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
                    <label for="preco_custo">Preço de custo (por unidade)</label>
                    <input type="number" id="preco_custo" name="preco_custo" value="<?= htmlspecialchars($valores['preco_custo']) ?>" min="0" step="0.01" placeholder="0,00">
                </div>

                <div>
                    <label for="nota_fiscal">Nota fiscal</label>
                    <input type="text" id="nota_fiscal" name="nota_fiscal" value="<?= htmlspecialchars($valores['nota_fiscal']) ?>" maxlength="60">
                </div>
            </div>

            <div style="display:flex; gap:10px;">
                <button type="submit">Salvar alterações</button>
                <a href="cadastro_entrada.php" class="btn btn--ghost">Voltar</a>
            </div>
        </form>
    </div>

    <a href="cadastro_entrada.php" class="back">← Voltar para entradas</a>
</main>

</body>
</html>
