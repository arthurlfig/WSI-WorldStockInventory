<?php
require_once __DIR__ . '/auth.php';
exigirLogin();

require_once __DIR__ . '/config/database.php';

$pdo = Database::getConnection();

$id = $_GET['id'] ?? '';

if ($id === '' || !ctype_digit((string) $id)) {
    header('Location: consulta_produtos.php');
    exit;
}

$categoriasLabels = [
    'roupa'      => 'Roupa',
    'cosmetico'  => 'Cosmético',
    'brinquedo'  => 'Brinquedo',
    'jogo'       => 'Jogo',
    'filme'      => 'Filme',
];

$tabelasPorCategoria = [
    'roupa'      => 'roupas',
    'cosmetico'  => 'cosmeticos',
    'brinquedo'  => 'brinquedos',
    'jogo'       => 'jogos',
    'filme'      => 'filmes',
];

$sexoLabels = [
    'masculino' => 'Masculino',
    'feminino'  => 'Feminino',
    'unissex'   => 'Unissex',
];

$categoriaCosmeticoLabels = [
    'perfume'           => 'Perfume',
    'maquiagem'         => 'Maquiagem',
    'skincare'          => 'Skincare',
    'capilar'           => 'Capilar',
    'cuidados_pessoais' => 'Cuidados pessoais',
];

$modoJogoLabels = [
    'single_player' => 'Single player',
    'multiplayer'   => 'Multiplayer',
    'ambos'         => 'Ambos',
];

$produto  = null;
$detalhes = null;
$erro     = '';

try {
    $stmt = $pdo->prepare(
        'SELECT p.*, f.nome AS fornecedor_nome
         FROM produtos p
         LEFT JOIN fornecedores f ON f.id = p.fornecedor_id
         WHERE p.id = :id'
    );
    $stmt->execute(['id' => $id]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($produto) {
        $tabela = $tabelasPorCategoria[$produto['categoria']] ?? null;

        if ($tabela) {
            $stmt = $pdo->prepare("SELECT * FROM {$tabela} WHERE produto_id = :produto_id");
            $stmt->execute(['produto_id' => $produto['id']]);
            $detalhes = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    $erro = 'Não foi possível carregar o produto.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $produto ? htmlspecialchars($produto['nome']) . ' — WSI' : 'Produto — WSI' ?></title>
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
        max-width: 760px;
        margin: 0 auto;
        padding: 40px 32px 60px;
    }

    .back {
        display: inline-block;
        color: var(--ink-dim);
        text-decoration: none;
        font-size: 13px;
        margin-bottom: 20px;
    }

    .back:hover { color: var(--accent); }

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
    }

    .vazio a { color: var(--accent); }

    /* ---------- Cabeçalho do produto ---------- */
    .produto-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 24px;
    }

    .badge {
        display: inline-block;
        font-family: var(--mono);
        font-size: 11px;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--accent);
        background: rgba(79,176,165,0.1);
        border: 1px solid var(--accent-dim);
        border-radius: 4px;
        padding: 3px 8px;
        margin-bottom: 10px;
    }

    .produto-header h1 {
        margin: 0;
        font-size: 26px;
        letter-spacing: -0.01em;
    }

    .preco-destaque {
        text-align: right;
        font-family: var(--mono);
        font-size: 22px;
        font-weight: 600;
        color: var(--accent);
        white-space: nowrap;
    }

    .preco-destaque span {
        display: block;
        font-size: 11px;
        color: var(--ink-dim);
        font-weight: 400;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    /* ---------- Cards de informação ---------- */
    .card {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: var(--radius);
        overflow: hidden;
        margin-bottom: 18px;
    }

    .card__header {
        padding: 14px 22px;
        border-bottom: 1px solid var(--panel-border);
    }

    .card__eyebrow {
        font-family: var(--mono);
        font-size: 11px;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--accent);
        margin: 0;
    }

    .card__body { padding: 18px 22px; }

    .descricao {
        font-size: 14px;
        line-height: 1.6;
        color: var(--ink);
        margin: 0;
    }

    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px 24px;
    }

    .info-item .info-label {
        font-family: var(--mono);
        font-size: 11px;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--ink-dim);
        margin: 0 0 4px;
    }

    .info-item .info-value {
        font-size: 14px;
        color: var(--ink);
        margin: 0;
    }

    .info-value.mono { font-family: var(--mono); }
    .info-value.vazio-inline { color: var(--ink-dim); }
</style>
</head>
<body>

<header>
    <p class="eyebrow">WSI</p>
    <nav>
        <a href="home.php">Início</a>
        <a href="produtos.php" class="active">Produtos</a>
        <a href="configuracoes.php">Configurações</a>
        <a href="perfil.php">Meu perfil</a>
        <a href="logout.php">Sair</a>
    </nav>
</header>

<main>
    <a href="produtos.php" class="back">← Voltar para produtos</a>

    <?php if ($erro): ?>
        <div class="msg--error"><?= htmlspecialchars($erro) ?></div>

    <?php elseif (!$produto): ?>
        <div class="vazio">
            Produto não encontrado. <a href="produtos.php">Voltar para produtos</a>.
        </div>

    <?php else: ?>

        <div class="produto-header">
            <div>
                <span class="badge">
                    <?= htmlspecialchars($categoriasLabels[$produto['categoria']] ?? $produto['categoria']) ?>
                </span>
                <h1><?= htmlspecialchars($produto['nome']) ?></h1>
            </div>
            <div class="preco-destaque">
                <span>Preço de venda</span>
                R$ <?= number_format((float) $produto['preco_venda'], 2, ',', '.') ?>
            </div>
        </div>

        <?php if (!empty($produto['descricao'])): ?>
            <div class="card">
                <div class="card__header">
                    <p class="card__eyebrow">Descrição</p>
                </div>
                <div class="card__body">
                    <p class="descricao"><?= nl2br(htmlspecialchars($produto['descricao'])) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Informações gerais -->
        <div class="card">
            <div class="card__header">
                <p class="card__eyebrow">Informações gerais</p>
            </div>
            <div class="card__body">
                <div class="info-grid">
                    <div class="info-item">
                        <p class="info-label">Código de barras</p>
                        <p class="info-value mono <?= empty($produto['codigo_barras']) ? 'vazio-inline' : '' ?>">
                            <?= !empty($produto['codigo_barras']) ? htmlspecialchars($produto['codigo_barras']) : 'Não informado' ?>
                        </p>
                    </div>

                    <div class="info-item">
                        <p class="info-label">Fornecedor</p>
                        <p class="info-value <?= empty($produto['fornecedor_nome']) ? 'vazio-inline' : '' ?>">
                            <?= !empty($produto['fornecedor_nome']) ? htmlspecialchars($produto['fornecedor_nome']) : 'Não informado' ?>
                        </p>
                    </div>

                    <div class="info-item">
                        <p class="info-label">Estoque mínimo</p>
                        <p class="info-value mono"><?= (int) $produto['estoque_minimo'] ?> unidades</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informações específicas da categoria -->
        <?php if ($detalhes): ?>
            <div class="card">
                <div class="card__header">
                    <p class="card__eyebrow">
                        Informações do<?= $produto['categoria'] === 'roupa' || $produto['categoria'] === 'cosmetico' ? 'a' : '' ?>
                        <?= htmlspecialchars(strtolower($categoriasLabels[$produto['categoria']] ?? '')) ?>
                    </p>
                </div>
                <div class="card__body">
                    <div class="info-grid">

                        <?php if ($produto['categoria'] === 'roupa'): ?>

                            <div class="info-item">
                                <p class="info-label">Tamanho</p>
                                <p class="info-value"><?= htmlspecialchars($detalhes['tamanho']) ?></p>
                            </div>

                            <div class="info-item">
                                <p class="info-label">Marca</p>
                                <p class="info-value <?= empty($detalhes['marca']) ? 'vazio-inline' : '' ?>">
                                    <?= !empty($detalhes['marca']) ? htmlspecialchars($detalhes['marca']) : 'Não informado' ?>
                                </p>
                            </div>

                            <div class="info-item">
                                <p class="info-label">Sexo</p>
                                <p class="info-value">
                                    <?= htmlspecialchars($sexoLabels[$detalhes['sexo']] ?? $detalhes['sexo']) ?>
                                </p>
                            </div>

                        <?php elseif ($produto['categoria'] === 'cosmetico'): ?>

                            <div class="info-item">
                                <p class="info-label">Categoria</p>
                                <p class="info-value">
                                    <?= htmlspecialchars($categoriaCosmeticoLabels[$detalhes['categoria_cosmetico']] ?? $detalhes['categoria_cosmetico']) ?>
                                </p>
                            </div>

                            <div class="info-item">
                                <p class="info-label">Tom / cor</p>
                                <p class="info-value <?= empty($detalhes['tom_cor']) ? 'vazio-inline' : '' ?>">
                                    <?= !empty($detalhes['tom_cor']) ? htmlspecialchars($detalhes['tom_cor']) : 'Não informado' ?>
                                </p>
                            </div>

                            <div class="info-item">
                                <p class="info-label">Quantidade</p>
                                <p class="info-value mono <?= $detalhes['quantidade_valor'] === null ? 'vazio-inline' : '' ?>">
                                    <?= $detalhes['quantidade_valor'] !== null
                                        ? htmlspecialchars($detalhes['quantidade_valor']) . ' ' . htmlspecialchars($detalhes['quantidade_unidade'])
                                        : 'Não informado' ?>
                                </p>
                            </div>

                            <div class="info-item">
                                <p class="info-label">Modo de aplicação</p>
                                <p class="info-value <?= empty($detalhes['modo_aplicacao']) ? 'vazio-inline' : '' ?>">
                                    <?= !empty($detalhes['modo_aplicacao']) ? htmlspecialchars($detalhes['modo_aplicacao']) : 'Não informado' ?>
                                </p>
                            </div>

                            <div class="info-item">
                                <p class="info-label">Registro ANVISA</p>
                                <p class="info-value mono <?= empty($detalhes['registro_anvisa']) ? 'vazio-inline' : '' ?>">
                                    <?= !empty($detalhes['registro_anvisa']) ? htmlspecialchars($detalhes['registro_anvisa']) : 'Não informado' ?>
                                </p>
                            </div>

                        <?php elseif ($produto['categoria'] === 'brinquedo'): ?>

                            <div class="info-item">
                                <p class="info-label">Classificação indicativa</p>
                                <p class="info-value"><?= htmlspecialchars($detalhes['classificacao_indicativa']) ?></p>
                            </div>

                            <div class="info-item">
                                <p class="info-label">Marca</p>
                                <p class="info-value <?= empty($detalhes['marca']) ? 'vazio-inline' : '' ?>">
                                    <?= !empty($detalhes['marca']) ? htmlspecialchars($detalhes['marca']) : 'Não informado' ?>
                                </p>
                            </div>

                            <div class="info-item">
                                <p class="info-label">Coleção</p>
                                <p class="info-value <?= empty($detalhes['colecao']) ? 'vazio-inline' : '' ?>">
                                    <?= !empty($detalhes['colecao']) ? htmlspecialchars($detalhes['colecao']) : 'Não informado' ?>
                                </p>
                            </div>

                        <?php elseif ($produto['categoria'] === 'jogo'): ?>

                            <div class="info-item">
                                <p class="info-label">Gênero</p>
                                <p class="info-value <?= empty($detalhes['genero']) ? 'vazio-inline' : '' ?>">
                                    <?= !empty($detalhes['genero']) ? htmlspecialchars($detalhes['genero']) : 'Não informado' ?>
                                </p>
                            </div>

                            <div class="info-item">
                                <p class="info-label">Classificação indicativa</p>
                                <p class="info-value <?= empty($detalhes['classificacao_indicativa']) ? 'vazio-inline' : '' ?>">
                                    <?= !empty($detalhes['classificacao_indicativa']) ? htmlspecialchars($detalhes['classificacao_indicativa']) : 'Não informado' ?>
                                </p>
                            </div>

                            <div class="info-item">
                                <p class="info-label">Desenvolvedora</p>
                                <p class="info-value <?= empty($detalhes['desenvolvedora']) ? 'vazio-inline' : '' ?>">
                                    <?= !empty($detalhes['desenvolvedora']) ? htmlspecialchars($detalhes['desenvolvedora']) : 'Não informado' ?>
                                </p>
                            </div>

                            <div class="info-item">
                                <p class="info-label">Plataforma</p>
                                <p class="info-value <?= empty($detalhes['plataforma']) ? 'vazio-inline' : '' ?>">
                                    <?= !empty($detalhes['plataforma']) ? htmlspecialchars($detalhes['plataforma']) : 'Não informado' ?>
                                </p>
                            </div>

                            <div class="info-item">
                                <p class="info-label">Modo de jogo</p>
                                <p class="info-value">
                                    <?= htmlspecialchars($modoJogoLabels[$detalhes['modo_jogo']] ?? $detalhes['modo_jogo']) ?>
                                </p>
                            </div>

                        <?php elseif ($produto['categoria'] === 'filme'): ?>

                            <div class="info-item">
                                <p class="info-label">Gênero</p>
                                <p class="info-value <?= empty($detalhes['genero']) ? 'vazio-inline' : '' ?>">
                                    <?= !empty($detalhes['genero']) ? htmlspecialchars($detalhes['genero']) : 'Não informado' ?>
                                </p>
                            </div>

                            <div class="info-item">
                                <p class="info-label">Classificação indicativa</p>
                                <p class="info-value <?= empty($detalhes['classificacao_indicativa']) ? 'vazio-inline' : '' ?>">
                                    <?= !empty($detalhes['classificacao_indicativa']) ? htmlspecialchars($detalhes['classificacao_indicativa']) : 'Não informado' ?>
                                </p>
                            </div>

                            <div class="info-item">
                                <p class="info-label">Duração</p>
                                <p class="info-value mono <?= empty($detalhes['duracao_minutos']) ? 'vazio-inline' : '' ?>">
                                    <?php if (!empty($detalhes['duracao_minutos'])):
                                        $min = (int) $detalhes['duracao_minutos'];
                                        $h   = intdiv($min, 60);
                                        $m   = $min % 60;
                                    ?>
                                        <?= $h > 0 ? $h . 'h ' : '' ?><?= $m ?>min
                                    <?php else: ?>
                                        Não informado
                                    <?php endif; ?>
                                </p>
                            </div>

                            <div class="info-item">
                                <p class="info-label">Data de lançamento</p>
                                <p class="info-value <?= empty($detalhes['data_lancamento']) ? 'vazio-inline' : '' ?>">
                                    <?= !empty($detalhes['data_lancamento'])
                                        ? htmlspecialchars(date('d/m/Y', strtotime($detalhes['data_lancamento'])))
                                        : 'Não informado' ?>
                                </p>
                            </div>

                        <?php endif; ?>

                    </div>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</main>

</body>
</html>