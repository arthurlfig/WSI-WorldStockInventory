<?php require_once __DIR__ . '/auth.php'; exigirLogin(); require_once __DIR__ . '/config/database.php';

$pdo = Database::getConnection();

$erros = [];
$sucesso = '';

$valores = [
    'lote_id' => '',
    'tipo' => 'saida',
    'quantidade' => '',
    'motivo' => '',
];

/*
 * Lotes com saldo disponível (ordenados por validade — dá prioridade
 * visual pra quem vence primeiro, tipo FEFO).
 */
$lotesDisponiveis = $pdo->query(
    "SELECT l.id, l.numero_lote, l.quantidade_disponivel, l.data_validade,
            p.id AS produto_id, p.nome AS produto_nome
     FROM lotes l
     JOIN produtos p ON p.id = l.produto_id
     WHERE l.quantidade_disponivel > 0 AND p.ativo = 1
     ORDER BY (l.data_validade IS NULL), l.data_validade, p.nome"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($valores as $campo => $valor) {
        if (isset($_POST[$campo])) {
            $valores[$campo] = trim($_POST[$campo]);
        }
    }

    /*
     * ==========================
     * VALIDAÇÕES
     * ==========================
     */
    if ($valores['lote_id'] === '' || !ctype_digit($valores['lote_id'])) {
        $erros[] = 'Selecione o lote de onde a saída vai sair.';
    }

    if (!in_array($valores['tipo'], ['saida', 'ajuste', 'perda_vencimento'], true)) {
        $erros[] = 'Selecione um tipo de saída válido.';
    }

    if (
        $valores['quantidade'] === '' ||
        !ctype_digit($valores['quantidade']) ||
        (int)$valores['quantidade'] <= 0
    ) {
        $erros[] = 'Informe uma quantidade válida (número inteiro maior que zero).';
    }

    if ($valores['motivo'] === '') {
        $erros[] = 'Informe o motivo da saída.';
    }

    /*
     * ==========================
     * GRAVAÇÃO
     * ==========================
     */
    if (empty($erros)) {
        try {
            $pdo->beginTransaction();

            // Trava a linha do lote para evitar corrida entre duas saídas simultâneas
            $stmt = $pdo->prepare('SELECT * FROM lotes WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $valores['lote_id']]);
            $lote = $stmt->fetch();

            if (!$lote) {
                $erros[] = 'Lote não encontrado.';
            } elseif ((int)$valores['quantidade'] > (int)$lote['quantidade_disponivel']) {
                $erros[] = 'Quantidade maior do que o disponível nesse lote (' . (int)$lote['quantidade_disponivel'] . ' unidades).';
            }

            if (empty($erros)) {
                $stmt = $pdo->prepare(
                    'UPDATE lotes SET quantidade_disponivel = quantidade_disponivel - :quantidade WHERE id = :id'
                );
                $stmt->execute([
                    'quantidade' => $valores['quantidade'],
                    'id' => $lote['id'],
                ]);

                $stmt = $pdo->prepare(
                    'INSERT INTO estoque_movimentacoes
                        (produto_id, lote_id, tipo, quantidade, motivo, usuario_id)
                     VALUES
                        (:produto_id, :lote_id, :tipo, :quantidade, :motivo, :usuario_id)'
                );

                $stmt->execute([
                    'produto_id' => $lote['produto_id'],
                    'lote_id' => $lote['id'],
                    'tipo' => $valores['tipo'],
                    'quantidade' => $valores['quantidade'],
                    'motivo' => $valores['motivo'],
                    'usuario_id' => $_SESSION['usuario_id'],
                ]);

                $pdo->commit();

                $sucesso = 'Saída registrada com sucesso!';

                foreach ($valores as $campo => $valor) {
                    $valores[$campo] = '';
                }
                $valores['tipo'] = 'saida';

                // Recarrega a lista de lotes disponíveis já com o saldo atualizado
                $lotesDisponiveis = $pdo->query(
                    "SELECT l.id, l.numero_lote, l.quantidade_disponivel, l.data_validade,
                            p.id AS produto_id, p.nome AS produto_nome
                     FROM lotes l
                     JOIN produtos p ON p.id = l.produto_id
                     WHERE l.quantidade_disponivel > 0 AND p.ativo = 1
                     ORDER BY (l.data_validade IS NULL), l.data_validade, p.nome"
                )->fetchAll();
            } else {
                $pdo->rollBack();
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erros[] = 'Não foi possível registrar a saída.';
        }
    }
}

/*
 * ==========================
 * ÚLTIMAS SAÍDAS (conferência)
 * ==========================
 */
$ultimasSaidas = $pdo->query(
    "SELECT m.id, m.tipo, m.quantidade, m.motivo, m.data_movimentacao,
            p.nome AS produto_nome, l.numero_lote, u.nome AS usuario_nome
     FROM estoque_movimentacoes m
     JOIN produtos p ON p.id = m.produto_id
     LEFT JOIN lotes l ON l.id = m.lote_id
     LEFT JOIN usuarios u ON u.id = m.usuario_id
     WHERE m.tipo IN ('saida', 'ajuste', 'perda_vencimento')
     ORDER BY m.data_movimentacao DESC
     LIMIT 15"
)->fetchAll();

$badgesTipo = [
    'saida' => 'badge--warn',
    'ajuste' => 'badge--muted',
    'perda_vencimento' => 'badge--danger',
];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cadastro de Saídas — WSI</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>

<main class="wide">
    <h1>Cadastro de saídas</h1>
    <p class="sub">Registre uma saída manual de estoque: perda, vencimento ou ajuste de contagem.</p>
    <p class="sub" style="margin-top:-22px;">Para dar baixa por venda, use <a href="cadastro_venda.php" style="color: var(--accent);">Registrar venda</a>.</p>

    <div class="card" style="margin-bottom: 24px;">
        <div class="card__header">
            <p class="card__eyebrow">Estoque · Nova saída</p>
            <h2 class="card__title">Registrar saída de lote</h2>
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

            <?php if (empty($lotesDisponiveis)): ?>
                <div class="msg msg--error">
                    Nenhum lote com saldo disponível no momento.
                </div>
            <?php endif; ?>

            <div class="field-grid">
                <div class="field-full">
                    <label for="lote_id">Produto / Lote</label>
                    <select id="lote_id" name="lote_id" required <?= empty($lotesDisponiveis) ? 'disabled' : '' ?>>
                        <option value="">Selecione...</option>
                        <?php foreach ($lotesDisponiveis as $l): ?>
                            <option value="<?= (int)$l['id'] ?>" <?= (string)$valores['lote_id'] === (string)$l['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($l['produto_nome']) ?> — Lote <?= htmlspecialchars($l['numero_lote']) ?>
                                (disp.: <?= (int)$l['quantidade_disponivel'] ?><?= $l['data_validade'] ? ', val.: ' . htmlspecialchars(date('d/m/Y', strtotime($l['data_validade']))) : '' ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="tipo">Tipo de saída</label>
                    <select id="tipo" name="tipo">
                        <option value="saida" <?= $valores['tipo'] === 'saida' ? 'selected' : '' ?>>Saída (uso interno / diversos)</option>
                        <option value="ajuste" <?= $valores['tipo'] === 'ajuste' ? 'selected' : '' ?>>Ajuste de contagem</option>
                        <option value="perda_vencimento" <?= $valores['tipo'] === 'perda_vencimento' ? 'selected' : '' ?>>Perda / vencimento</option>
                    </select>
                </div>

                <div>
                    <label for="quantidade">Quantidade</label>
                    <input type="number" id="quantidade" name="quantidade" value="<?= htmlspecialchars($valores['quantidade']) ?>" min="1" step="1" required>
                </div>

                <div class="field-full">
                    <label for="motivo">Motivo</label>
                    <input type="text" id="motivo" name="motivo" value="<?= htmlspecialchars($valores['motivo']) ?>" maxlength="255" placeholder="Ex: produto danificado, contagem física, uso interno..." required>
                </div>
            </div>

            <button type="submit" <?= empty($lotesDisponiveis) ? 'disabled' : '' ?>>Registrar saída</button>
        </form>
    </div>

    <div class="card">
        <div class="card__header">
            <p class="card__eyebrow">Conferência</p>
            <h2 class="card__title">Últimas saídas registradas</h2>
        </div>
        <div class="table-wrap">
            <?php if (empty($ultimasSaidas)): ?>
                <p class="empty-state">Nenhuma saída registrada ainda.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Lote</th>
                            <th>Tipo</th>
                            <th>Qtde.</th>
                            <th>Motivo</th>
                            <th>Usuário</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimasSaidas as $s): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['produto_nome']) ?></td>
                                <td><?= htmlspecialchars($s['numero_lote'] ?: '—') ?></td>
                                <td>
                                    <span class="badge <?= $badgesTipo[$s['tipo']] ?? 'badge--muted' ?>">
                                        <?= htmlspecialchars(str_replace('_', ' ', $s['tipo'])) ?>
                                    </span>
                                </td>
                                <td><?= (int)$s['quantidade'] ?></td>
                                <td><?= htmlspecialchars($s['motivo'] ?: '—') ?></td>
                                <td><?= htmlspecialchars($s['usuario_nome'] ?: '—') ?></td>
                                <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($s['data_movimentacao']))) ?></td>
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
