<?php
require_once __DIR__ . '/auth.php';
exigirLogin();

require_once __DIR__ . '/config/database.php';

$pdo = Database::getConnection();

$notificacoes = [];
$erro = '';

try {
    $stmt = $pdo->query(
        "SELECT
            m.id,
            m.tipo,
            m.quantidade,
            m.motivo,
            m.data_movimentacao,
            p.nome AS produto_nome,
            u.nome AS usuario_nome
         FROM estoque_movimentacoes m
         INNER JOIN produtos p ON p.id = m.produto_id
         LEFT JOIN usuarios u ON u.id = m.usuario_id
         WHERE m.tipo IN ('entrada', 'saida')
         ORDER BY m.data_movimentacao DESC
         LIMIT 100"
    );
    $notificacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $erro = 'Não foi possível carregar as notificações.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notificações — WSI</title>
<style>
    :root {
        --bg: #14181f;
        --panel: #1c222c;
        --panel-border: #2b3340;
        --ink: #e8ebf0;
        --ink-dim: #8b96a8;
        --accent: #4fb0a5;
        --accent-dim: #35766f;
        --danger: #e2665a;
        --field-bg: #10141a;
        --radius: 6px;
        --mono: 'JetBrains Mono', 'Courier New', monospace;
        --sans: 'Inter', 'Segoe UI', system-ui, sans-serif;
    }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        min-height: 100vh;
        background:
            linear-gradient(180deg, rgba(79,176,165,0.06), transparent 320px),
            var(--bg);
        color: var(--ink);
        font-family: var(--sans);
    }

    header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 18px 32px;
        border-bottom: 1px solid var(--panel-border);
    }

    header .eyebrow {
        font-family: var(--mono);
        font-size: 11px;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: var(--accent);
        margin: 0;
    }

    header nav a {
        color: var(--ink-dim);
        text-decoration: none;
        font-size: 13px;
        margin-left: 18px;
    }

    header nav a:hover,
    header nav a.active { color: var(--accent); }

    main {
        max-width: 720px;
        margin: 0 auto;
        padding: 40px 32px 60px;
    }

    main h1 { font-size: 26px; margin: 0 0 6px; }
    main p.sub { color: var(--ink-dim); margin: 0 0 28px; }

    .msg--error {
        background: rgba(226,102,90,0.1);
        border: 1px solid var(--danger);
        color: var(--danger);
        border-radius: var(--radius);
        padding: 12px 14px;
        font-size: 13px;
    }

    .vazio {
        text-align: center;
        padding: 60px 20px;
        color: var(--ink-dim);
        font-size: 14px;
    }

    .lista {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    /* ---------- Caixinha de notificação ---------- */
    .notificacao {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        padding: 14px 18px;
        border-radius: var(--radius);
        border: 1px solid transparent;
    }

    .notificacao--entrada {
        background: rgba(79,176,165,0.1);
        border-color: var(--accent-dim);
    }

    .notificacao--saida {
        background: rgba(226,102,90,0.1);
        border-color: var(--danger);
    }

    .notificacao__icone {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: var(--mono);
        font-size: 16px;
        font-weight: 700;
        flex-shrink: 0;
    }

    .notificacao--entrada .notificacao__icone {
        background: rgba(79,176,165,0.2);
        color: var(--accent);
    }

    .notificacao--saida .notificacao__icone {
        background: rgba(226,102,90,0.2);
        color: var(--danger);
    }

    .notificacao__corpo { flex: 1; }

    .notificacao__titulo {
        margin: 0 0 2px;
        font-size: 14px;
        font-weight: 600;
    }

    .notificacao--entrada .notificacao__titulo span.qtd { color: var(--accent); }
    .notificacao--saida .notificacao__titulo span.qtd { color: var(--danger); }

    .notificacao__detalhe {
        margin: 0;
        font-size: 12px;
        color: var(--ink-dim);
    }

    .notificacao__data {
        font-family: var(--mono);
        font-size: 11px;
        color: var(--ink-dim);
        white-space: nowrap;
        margin-left: 8px;
    }
</style>
</head>
<body>
<?php include __DIR__ . '/partials/navbar.php'; ?>
<main>
    <h1>Notificações</h1>
    <p class="sub">Entradas e saídas de produtos no estoque.</p>

    <?php if ($erro): ?>
        <div class="msg--error"><?= htmlspecialchars($erro) ?></div>

    <?php elseif (empty($notificacoes)): ?>
        <div class="vazio">Nenhuma movimentação de estoque registrada ainda.</div>

    <?php else: ?>
        <div class="lista">
            <?php foreach ($notificacoes as $n): ?>
                <?php $ehEntrada = $n['tipo'] === 'entrada'; ?>
                <div class="notificacao <?= $ehEntrada ? 'notificacao--entrada' : 'notificacao--saida' ?>">
                    <div class="notificacao__icone"><?= $ehEntrada ? '+' : '−' ?></div>
                    <div class="notificacao__corpo">
                        <p class="notificacao__titulo">
                            <span class="qtd"><?= $ehEntrada ? '+' : '-' ?><?= (int) $n['quantidade'] ?></span>
                            <?= htmlspecialchars($n['produto_nome']) ?>
                            <?= $ehEntrada ? 'adicionado ao estoque' : 'removido do estoque' ?>
                        </p>
                        <p class="notificacao__detalhe">
                            <?php if (!$ehEntrada && !empty($n['motivo'])): ?>
                                Motivo: <?= htmlspecialchars($n['motivo']) ?> ·
                            <?php endif; ?>
                            <?= !empty($n['usuario_nome']) ? 'por ' . htmlspecialchars($n['usuario_nome']) : 'Usuário não identificado' ?>
                        </p>
                    </div>
                    <span class="notificacao__data">
                        <?= htmlspecialchars(date('d/m/Y H:i', strtotime($n['data_movimentacao']))) ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

</body>
</html>
