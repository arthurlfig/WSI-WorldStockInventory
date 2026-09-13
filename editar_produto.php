<?php require_once __DIR__ . '/auth.php'; exigirLogin(); require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/repositories/ProdutoRepository.php';

$pdo = Database::getConnection();
$produtoRepository = new ProdutoRepository($pdo);

/* O id vem da querystring (GET, ao abrir a tela) ou do campo oculto do formulário (POST, ao salvar) */

$id = $_POST['id'] ?? $_GET['id'] ?? '';

if ($id === '' || !ctype_digit((string) $id)) {
    header('Location: produtos.php');
    exit;
}

$id = (int) $id;

$produtoExistente = $produtoRepository->buscarPorId($id);

if (!$produtoExistente) {
    header('Location: produtos.php');
    exit;
}

$erros = [];
$sucesso = '';

$categoriaOriginal = $produtoExistente['categoria'];

$valores = [
    'nome' => (string) ($produtoExistente['nome'] ?? ''),
    'descricao' => (string) ($produtoExistente['descricao'] ?? ''),
    'categoria' => $categoriaOriginal,
    'codigo_barras' => (string) ($produtoExistente['codigo_barras'] ?? ''),
    'preco_venda' => (string) ($produtoExistente['preco_venda'] ?? ''),
    'fornecedor_id' => (string) ($produtoExistente['fornecedor_id'] ?? ''),
    'estoque_minimo' => (string) ($produtoExistente['estoque_minimo'] ?? ''),

    // Roupa
    'tamanho' => (string) ($produtoExistente['tamanho'] ?? ''),
    'marca_roupa' => (string) ($produtoExistente['marca_roupa'] ?? ''),
    'sexo' => (string) ($produtoExistente['sexo'] ?? 'unissex'),

    // Cosmético
    'categoria_cosmetico' => (string) ($produtoExistente['categoria_cosmetico'] ?? ''),
    'tom_cor' => (string) ($produtoExistente['tom_cor'] ?? ''),
    'quantidade_valor' => (string) ($produtoExistente['quantidade_valor'] ?? ''),
    'quantidade_unidade' => (string) ($produtoExistente['quantidade_unidade'] ?? 'ml'),
    'modo_aplicacao' => (string) ($produtoExistente['modo_aplicacao'] ?? ''),
    'registro_anvisa' => (string) ($produtoExistente['registro_anvisa'] ?? ''),

    // Brinquedo
    'classificacao_brinquedo' => (string) ($produtoExistente['classificacao_brinquedo'] ?? ''),
    'marca_brinquedo' => (string) ($produtoExistente['marca_brinquedo'] ?? ''),
    'colecao' => (string) ($produtoExistente['colecao'] ?? ''),

    // Jogo
    'genero_jogo' => (string) ($produtoExistente['genero_jogo'] ?? ''),
    'classificacao_jogo' => (string) ($produtoExistente['classificacao_jogo'] ?? ''),
    'desenvolvedora' => (string) ($produtoExistente['desenvolvedora'] ?? ''),
    'plataforma' => (string) ($produtoExistente['plataforma'] ?? ''),
    'modo_jogo' => (string) ($produtoExistente['modo_jogo'] ?? 'single_player'),

    // Filme
    'genero_filme' => (string) ($produtoExistente['genero_filme'] ?? ''),
    'classificacao_filme' => (string) ($produtoExistente['classificacao_filme'] ?? ''),
    'duracao_minutos' => (string) ($produtoExistente['duracao_minutos'] ?? ''),
    'data_lancamento' => (string) ($produtoExistente['data_lancamento'] ?? '')
];

$fornecedores = [];

try {
    $stmt = $pdo->query(
        'SELECT id, nome
         FROM fornecedores
         WHERE ativo = 1
         ORDER BY nome'
    );

    $fornecedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $erros[] = 'Não foi possível carregar os fornecedores.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($valores as $campo => $valor) {
        if (isset($_POST[$campo])) {
            $valores[$campo] = trim($_POST[$campo]);
        }
    }

    $valores['categoria'] = $categoriaOriginal;

    /*
     * ==========================
     * VALIDAÇÕES GERAIS
     * ==========================
     */

    if ($valores['nome'] === '') {
        $erros[] = 'Informe o nome do produto.';
    }

    if ($valores['categoria'] === '') {
        $erros[] = 'Selecione uma categoria.';
    }

    $categoriasValidas = [
        'roupa',
        'cosmetico',
        'brinquedo',
        'jogo',
        'filme'
    ];

    if (
        $valores['categoria'] !== '' &&
        !in_array($valores['categoria'], $categoriasValidas, true)
    ) {
        $erros[] = 'Categoria inválida.';
    }

    if (
        $valores['preco_venda'] !== '' &&
        (!is_numeric($valores['preco_venda']) || $valores['preco_venda'] < 0)
    ) {
        $erros[] = 'Informe um preço de venda válido.';
    }

    if (
        $valores['estoque_minimo'] !== '' &&
        (!ctype_digit($valores['estoque_minimo']) || (int)$valores['estoque_minimo'] < 0)
    ) {
        $erros[] = 'O estoque mínimo deve ser um número inteiro maior ou igual a zero.';
    }

    /*
     * ==========================
     * VALIDAÇÕES ESPECÍFICAS
     * ==========================
     */

    if ($valores['categoria'] === 'roupa') {

        if ($valores['tamanho'] === '') {
            $erros[] = 'Informe o tamanho da roupa.';
        }

        $sexosValidos = ['masculino', 'feminino', 'unissex'];

        if (!in_array($valores['sexo'], $sexosValidos, true)) {
            $erros[] = 'Selecione um sexo válido para a roupa.';
        }
    }

    if ($valores['categoria'] === 'cosmetico') {

        $categoriasCosmeticoValidas = [
            'perfume',
            'maquiagem',
            'skincare',
            'capilar',
            'cuidados_pessoais'
        ];

        if (
            !in_array(
                $valores['categoria_cosmetico'],
                $categoriasCosmeticoValidas,
                true
            )
        ) {
            $erros[] = 'Selecione uma categoria de cosmético válida.';
        }

        $unidadesValidas = ['ml', 'g', 'un'];

        if (
            !in_array(
                $valores['quantidade_unidade'],
                $unidadesValidas,
                true
            )
        ) {
            $erros[] = 'Selecione uma unidade válida.';
        }

        if (
            $valores['quantidade_valor'] !== '' &&
            (!is_numeric($valores['quantidade_valor']) ||
             $valores['quantidade_valor'] < 0)
        ) {
            $erros[] = 'Informe uma quantidade válida para o cosmético.';
        }
    }

    if ($valores['categoria'] === 'brinquedo') {

        if ($valores['classificacao_brinquedo'] === '') {
            $erros[] = 'Informe a classificação indicativa do brinquedo.';
        }
    }

    if ($valores['categoria'] === 'jogo') {

        $modosValidos = [
            'single_player',
            'multiplayer',
            'ambos'
        ];

        if (!in_array($valores['modo_jogo'], $modosValidos, true)) {
            $erros[] = 'Selecione um modo de jogo válido.';
        }
    }

    if ($valores['categoria'] === 'filme') {

        if (
            $valores['duracao_minutos'] !== '' &&
            (!ctype_digit($valores['duracao_minutos']) ||
             (int)$valores['duracao_minutos'] <= 0)
        ) {
            $erros[] = 'A duração do filme deve ser um número inteiro positivo.';
        }
    }

    /*
     * ==========================
     * CADASTRO
     * ==========================
     */

    if (empty($erros)) {

        try {

            $pdo->beginTransaction();

            /*
             * Verifica código de barras duplicado
             */
            if ($valores['codigo_barras'] !== '') {

                $stmt = $pdo->prepare(
                    'SELECT id
                     FROM produtos
                     WHERE codigo_barras = :codigo_barras
                       AND id != :id'
                );

                $stmt->execute([
                    'codigo_barras' => $valores['codigo_barras'],
                    'id' => $id
                ]);

                if ($stmt->fetch()) {
                    $erros[] = 'Já existe um produto cadastrado com esse código de barras.';
                }
            }

            if (empty($erros)) {

                /*
                 * Atualiza a tabela principal
                 */
                $stmt = $pdo->prepare(
                    'UPDATE produtos SET
                        nome = :nome,
                        descricao = :descricao,
                        codigo_barras = :codigo_barras,
                        preco_venda = :preco_venda,
                        fornecedor_id = :fornecedor_id,
                        estoque_minimo = :estoque_minimo
                    WHERE id = :id'
                );

                $stmt->execute([
                    'nome' => $valores['nome'],
                    'descricao' => $valores['descricao'] !== ''
                        ? $valores['descricao']
                        : null,
                    'codigo_barras' => $valores['codigo_barras'] !== ''
                        ? $valores['codigo_barras']
                        : null,
                    'preco_venda' => $valores['preco_venda'] !== ''
                        ? $valores['preco_venda']
                        : 0,
                    'fornecedor_id' => $valores['fornecedor_id'] !== ''
                        ? $valores['fornecedor_id']
                        : null,
                    'estoque_minimo' => $valores['estoque_minimo'] !== ''
                        ? $valores['estoque_minimo']
                        : 0,
                    'id' => $id
                ]);

                $produtoId = $id;

                /*
                 * ==========================
                 * DADOS DA CATEGORIA
                 * ==========================
                 */

                switch ($valores['categoria']) {

                    case 'roupa':

                        $stmt = $pdo->prepare(
                            'UPDATE roupas SET
                                tamanho = :tamanho,
                                marca = :marca,
                                sexo = :sexo
                            WHERE produto_id = :produto_id'
                        );

                        $stmt->execute([
                            'produto_id' => $produtoId,
                            'tamanho' => $valores['tamanho'],
                            'marca' => $valores['marca_roupa'] !== ''
                                ? $valores['marca_roupa']
                                : null,
                            'sexo' => $valores['sexo']
                        ]);

                        break;

                    case 'cosmetico':

                        $stmt = $pdo->prepare(
                            'UPDATE cosmeticos SET
                                categoria_cosmetico = :categoria_cosmetico,
                                tom_cor = :tom_cor,
                                quantidade_valor = :quantidade_valor,
                                quantidade_unidade = :quantidade_unidade,
                                modo_aplicacao = :modo_aplicacao,
                                registro_anvisa = :registro_anvisa
                            WHERE produto_id = :produto_id'
                        );

                        $stmt->execute([
                            'produto_id' => $produtoId,
                            'categoria_cosmetico' => $valores['categoria_cosmetico'],
                            'tom_cor' => $valores['tom_cor'] !== ''
                                ? $valores['tom_cor']
                                : null,
                            'quantidade_valor' => $valores['quantidade_valor'] !== ''
                                ? $valores['quantidade_valor']
                                : null,
                            'quantidade_unidade' => $valores['quantidade_unidade'],
                            'modo_aplicacao' => $valores['modo_aplicacao'] !== ''
                                ? $valores['modo_aplicacao']
                                : null,
                            'registro_anvisa' => $valores['registro_anvisa'] !== ''
                                ? $valores['registro_anvisa']
                                : null
                        ]);

                        break;

                    case 'brinquedo':

                        $stmt = $pdo->prepare(
                            'UPDATE brinquedos SET
                                classificacao_indicativa = :classificacao_indicativa,
                                marca = :marca,
                                colecao = :colecao
                            WHERE produto_id = :produto_id'
                        );

                        $stmt->execute([
                            'produto_id' => $produtoId,
                            'classificacao_indicativa' =>
                                $valores['classificacao_brinquedo'],
                            'marca' =>
                                $valores['marca_brinquedo'] !== ''
                                    ? $valores['marca_brinquedo']
                                    : null,
                            'colecao' =>
                                $valores['colecao'] !== ''
                                    ? $valores['colecao']
                                    : null
                        ]);

                        break;

                    case 'jogo':

                        $stmt = $pdo->prepare(
                            'UPDATE jogos SET
                                genero = :genero,
                                classificacao_indicativa = :classificacao_indicativa,
                                desenvolvedora = :desenvolvedora,
                                plataforma = :plataforma,
                                modo_jogo = :modo_jogo
                            WHERE produto_id = :produto_id'
                        );

                        $stmt->execute([
                            'produto_id' => $produtoId,
                            'genero' =>
                                $valores['genero_jogo'] !== ''
                                    ? $valores['genero_jogo']
                                    : null,
                            'classificacao_indicativa' =>
                                $valores['classificacao_jogo'] !== ''
                                    ? $valores['classificacao_jogo']
                                    : null,
                            'desenvolvedora' =>
                                $valores['desenvolvedora'] !== ''
                                    ? $valores['desenvolvedora']
                                    : null,
                            'plataforma' =>
                                $valores['plataforma'] !== ''
                                    ? $valores['plataforma']
                                    : null,
                            'modo_jogo' => $valores['modo_jogo']
                        ]);

                        break;

                    case 'filme':

                        $stmt = $pdo->prepare(
                            'UPDATE filmes SET
                                genero = :genero,
                                classificacao_indicativa = :classificacao_indicativa,
                                duracao_minutos = :duracao_minutos,
                                data_lancamento = :data_lancamento
                            WHERE produto_id = :produto_id'
                        );

                        $stmt->execute([
                            'produto_id' => $produtoId,
                            'genero' =>
                                $valores['genero_filme'] !== ''
                                    ? $valores['genero_filme']
                                    : null,
                            'classificacao_indicativa' =>
                                $valores['classificacao_filme'] !== ''
                                    ? $valores['classificacao_filme']
                                    : null,
                            'duracao_minutos' =>
                                $valores['duracao_minutos'] !== ''
                                    ? $valores['duracao_minutos']
                                    : null,
                            'data_lancamento' =>
                                $valores['data_lancamento'] !== ''
                                    ? $valores['data_lancamento']
                                    : null
                        ]);

                        break;
                }

                $pdo->commit();

                $sucesso = 'Produto atualizado com sucesso!';
            }

            if (!empty($erros) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $erros[] = 'Não foi possível salvar as alterações do produto.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Editar Produto — WSI</title>

<link rel="stylesheet" href="assets/css/app.css">
</head>

<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>

<main>
<div class="card card--wide">

    <div class="card__header">

        <p class="card__eyebrow">
            Produtos · Edição
        </p>

        <h1 class="card__title">
            Editar produto
        </h1>

        <p class="card__subtitle">
            Atualize as informações do produto. A categoria não pode ser alterada.
        </p>

    </div>

    <form method="POST" action="" novalidate>

        <input type="hidden" name="id" value="<?= (int) $id ?>">

        <?php if (!empty($erros)): ?>

            <div class="msg msg--error">

                <strong>Corrija os itens abaixo:</strong>

                <ul>

                    <?php foreach ($erros as $e): ?>

                        <li>
                            <?= htmlspecialchars($e) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <?php if ($sucesso): ?>

            <div class="msg msg--success">
                <?= htmlspecialchars($sucesso) ?>
            </div>

        <?php endif; ?>


        <!-- DADOS GERAIS -->

        <section class="section">

            <h2 class="section-title">
                Dados gerais
            </h2>

            <div class="field-grid">

                <div class="field-full">

                    <label for="nome">
                        Nome do produto
                    </label>

                    <input
                        type="text"
                        id="nome"
                        name="nome"
                        value="<?= htmlspecialchars($valores['nome']) ?>"
                        maxlength="180"
                        required
                    >

                </div>


                <div class="field-full">

                    <label for="descricao">
                        Descrição
                    </label>

                    <textarea
                        id="descricao"
                        name="descricao"
                    ><?= htmlspecialchars($valores['descricao']) ?></textarea>

                </div>


                <div>

                    <label for="categoria">
                        Categoria
                    </label>

                    <select
                        id="categoria"
                        disabled
                    >

                        <option value="roupa"
                            <?= $valores['categoria'] === 'roupa' ? 'selected' : '' ?>>
                            Roupa
                        </option>

                        <option value="cosmetico"
                            <?= $valores['categoria'] === 'cosmetico' ? 'selected' : '' ?>>
                            Cosmético
                        </option>

                        <option value="brinquedo"
                            <?= $valores['categoria'] === 'brinquedo' ? 'selected' : '' ?>>
                            Brinquedo
                        </option>

                        <option value="jogo"
                            <?= $valores['categoria'] === 'jogo' ? 'selected' : '' ?>>
                            Jogo
                        </option>

                        <option value="filme"
                            <?= $valores['categoria'] === 'filme' ? 'selected' : '' ?>>
                            Filme
                        </option>

                    </select>

                    <!-- categoria não é editável: valor real vai pelo campo oculto abaixo -->
                    <input type="hidden" name="categoria" value="<?= htmlspecialchars($valores['categoria']) ?>">

                </div>


                <div>

                    <label for="codigo_barras">
                        Código de barras
                    </label>

                    <input
                        type="text"
                        id="codigo_barras"
                        name="codigo_barras"
                        value="<?= htmlspecialchars($valores['codigo_barras']) ?>"
                        maxlength="50"
                    >

                </div>


                <div>

                    <label for="preco_venda">
                        Preço de venda
                    </label>

                    <input
                        type="number"
                        id="preco_venda"
                        name="preco_venda"
                        value="<?= htmlspecialchars($valores['preco_venda']) ?>"
                        min="0"
                        step="0.01"
                        placeholder="0,00"
                    >

                </div>


                <div>

                    <label for="estoque_minimo">
                        Estoque mínimo
                    </label>

                    <input
                        type="number"
                        id="estoque_minimo"
                        name="estoque_minimo"
                        value="<?= htmlspecialchars($valores['estoque_minimo']) ?>"
                        min="0"
                        step="1"
                        placeholder="0"
                    >

                </div>


                <div class="field-full">

                    <label for="fornecedor_id">
                        Fornecedor
                    </label>

                    <select
                        id="fornecedor_id"
                        name="fornecedor_id"
                    >

                        <option value="">
                            Nenhum fornecedor
                        </option>

                        <?php foreach ($fornecedores as $fornecedor): ?>

                            <option
                                value="<?= $fornecedor['id'] ?>"
                                <?= (string)$valores['fornecedor_id'] === (string)$fornecedor['id'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($fornecedor['nome']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>

        </section>


        <!-- ROUPA -->

        <section
            class="section category-fields"
            id="fields-roupa"
        >

            <h2 class="section-title">
                Informações da roupa
            </h2>

            <div class="field-grid">

                <div>

                    <label for="tamanho">
                        Tamanho
                    </label>

                    <input
                        type="text"
                        id="tamanho"
                        name="tamanho"
                        value="<?= htmlspecialchars($valores['tamanho']) ?>"
                        placeholder="P, M, G, 38..."
                    >

                </div>


                <div>

                    <label for="marca_roupa">
                        Marca
                    </label>

                    <input
                        type="text"
                        id="marca_roupa"
                        name="marca_roupa"
                        value="<?= htmlspecialchars($valores['marca_roupa']) ?>"
                    >

                </div>


                <div>

                    <label for="sexo">
                        Público
                    </label>

                    <select id="sexo" name="sexo">

                        <option value="unissex"
                            <?= $valores['sexo'] === 'unissex' ? 'selected' : '' ?>>
                            Unissex
                        </option>

                        <option value="masculino"
                            <?= $valores['sexo'] === 'masculino' ? 'selected' : '' ?>>
                            Masculino
                        </option>

                        <option value="feminino"
                            <?= $valores['sexo'] === 'feminino' ? 'selected' : '' ?>>
                            Feminino
                        </option>

                    </select>

                </div>

            </div>

        </section>


        <!-- COSMÉTICO -->

        <section
            class="section category-fields"
            id="fields-cosmetico"
        >

            <h2 class="section-title">
                Informações do cosmético
            </h2>

            <div class="field-grid">

                <div>

                    <label for="categoria_cosmetico">
                        Tipo de cosmético
                    </label>

                    <select
                        id="categoria_cosmetico"
                        name="categoria_cosmetico"
                    >

                        <option value="">
                            Selecione...
                        </option>

                        <option value="perfume"
                            <?= $valores['categoria_cosmetico'] === 'perfume' ? 'selected' : '' ?>>
                            Perfume
                        </option>

                        <option value="maquiagem"
                            <?= $valores['categoria_cosmetico'] === 'maquiagem' ? 'selected' : '' ?>>
                            Maquiagem
                        </option>

                        <option value="skincare"
                            <?= $valores['categoria_cosmetico'] === 'skincare' ? 'selected' : '' ?>>
                            Skincare
                        </option>

                        <option value="capilar"
                            <?= $valores['categoria_cosmetico'] === 'capilar' ? 'selected' : '' ?>>
                            Capilar
                        </option>

                        <option value="cuidados_pessoais"
                            <?= $valores['categoria_cosmetico'] === 'cuidados_pessoais' ? 'selected' : '' ?>>
                            Cuidados pessoais
                        </option>

                    </select>

                </div>


                <div>

                    <label for="tom_cor">
                        Tom / Cor
                    </label>

                    <input
                        type="text"
                        id="tom_cor"
                        name="tom_cor"
                        value="<?= htmlspecialchars($valores['tom_cor']) ?>"
                        placeholder="Bege claro, N4..."
                    >

                </div>


                <div>

                    <label for="quantidade_valor">
                        Quantidade
                    </label>

                    <input
                        type="number"
                        id="quantidade_valor"
                        name="quantidade_valor"
                        value="<?= htmlspecialchars($valores['quantidade_valor']) ?>"
                        min="0"
                        step="0.01"
                    >

                </div>


                <div>

                    <label for="quantidade_unidade">
                        Unidade
                    </label>

                    <select
                        id="quantidade_unidade"
                        name="quantidade_unidade"
                    >

                        <option value="ml"
                            <?= $valores['quantidade_unidade'] === 'ml' ? 'selected' : '' ?>>
                            ml
                        </option>

                        <option value="g"
                            <?= $valores['quantidade_unidade'] === 'g' ? 'selected' : '' ?>>
                            g
                        </option>

                        <option value="un"
                            <?= $valores['quantidade_unidade'] === 'un' ? 'selected' : '' ?>>
                            unidade
                        </option>

                    </select>

                </div>


                <div class="field-full">

                    <label for="modo_aplicacao">
                        Modo de aplicação
                    </label>

                    <input
                        type="text"
                        id="modo_aplicacao"
                        name="modo_aplicacao"
                        value="<?= htmlspecialchars($valores['modo_aplicacao']) ?>"
                        placeholder="Aplicar no rosto limpo..."
                    >

                </div>


                <div>

                    <label for="registro_anvisa">
                        Registro ANVISA
                    </label>

                    <input
                        type="text"
                        id="registro_anvisa"
                        name="registro_anvisa"
                        value="<?= htmlspecialchars($valores['registro_anvisa']) ?>"
                    >

                </div>

            </div>

        </section>


        <!-- BRINQUEDO -->

        <section
            class="section category-fields"
            id="fields-brinquedo"
        >

            <h2 class="section-title">
                Informações do brinquedo
            </h2>

            <div class="field-grid">

                <div>

                    <label for="classificacao_brinquedo">
                        Classificação indicativa
                    </label>

                    <input
                        type="text"
                        id="classificacao_brinquedo"
                        name="classificacao_brinquedo"
                        value="<?= htmlspecialchars($valores['classificacao_brinquedo']) ?>"
                        placeholder="0+, 3+, 12+..."
                    >

                </div>


                <div>

                    <label for="marca_brinquedo">
                        Marca
                    </label>

                    <input
                        type="text"
                        id="marca_brinquedo"
                        name="marca_brinquedo"
                        value="<?= htmlspecialchars($valores['marca_brinquedo']) ?>"
                    >

                </div>


                <div class="field-full">

                    <label for="colecao">
                        Coleção
                    </label>

                    <input
                        type="text"
                        id="colecao"
                        name="colecao"
                        value="<?= htmlspecialchars($valores['colecao']) ?>"
                    >

                </div>

            </div>

        </section>


        <!-- JOGO -->

        <section
            class="section category-fields"
            id="fields-jogo"
        >

            <h2 class="section-title">
                Informações do jogo
            </h2>

            <div class="field-grid">

                <div>

                    <label for="genero_jogo">
                        Gênero
                    </label>

                    <input
                        type="text"
                        id="genero_jogo"
                        name="genero_jogo"
                        value="<?= htmlspecialchars($valores['genero_jogo']) ?>"
                        placeholder="RPG, ação, aventura..."
                    >

                </div>


                <div>

                    <label for="classificacao_jogo">
                        Classificação indicativa
                    </label>

                    <input
                        type="text"
                        id="classificacao_jogo"
                        name="classificacao_jogo"
                        value="<?= htmlspecialchars($valores['classificacao_jogo']) ?>"
                        placeholder="L, 10, 12, 14, 16, 18"
                    >

                </div>


                <div>

                    <label for="desenvolvedora">
                        Desenvolvedora
                    </label>

                    <input
                        type="text"
                        id="desenvolvedora"
                        name="desenvolvedora"
                        value="<?= htmlspecialchars($valores['desenvolvedora']) ?>"
                    >

                </div>


                <div>

                    <label for="plataforma">
                        Plataforma
                    </label>

                    <input
                        type="text"
                        id="plataforma"
                        name="plataforma"
                        value="<?= htmlspecialchars($valores['plataforma']) ?>"
                        placeholder="PC, PS5, Xbox, Switch..."
                    >

                </div>


                <div>

                    <label for="modo_jogo">
                        Modo de jogo
                    </label>

                    <select id="modo_jogo" name="modo_jogo">

                        <option value="single_player"
                            <?= $valores['modo_jogo'] === 'single_player' ? 'selected' : '' ?>>
                            Single player
                        </option>

                        <option value="multiplayer"
                            <?= $valores['modo_jogo'] === 'multiplayer' ? 'selected' : '' ?>>
                            Multiplayer
                        </option>

                        <option value="ambos"
                            <?= $valores['modo_jogo'] === 'ambos' ? 'selected' : '' ?>>
                            Ambos
                        </option>

                    </select>

                </div>

            </div>

        </section>


        <!-- FILME -->

        <section
            class="section category-fields"
            id="fields-filme"
        >

            <h2 class="section-title">
                Informações do filme
            </h2>

            <div class="field-grid">

                <div>

                    <label for="genero_filme">
                        Gênero
                    </label>

                    <input
                        type="text"
                        id="genero_filme"
                        name="genero_filme"
                        value="<?= htmlspecialchars($valores['genero_filme']) ?>"
                        placeholder="Ação, drama, comédia..."
                    >

                </div>


                <div>

                    <label for="classificacao_filme">
                        Classificação indicativa
                    </label>

                    <input
                        type="text"
                        id="classificacao_filme"
                        name="classificacao_filme"
                        value="<?= htmlspecialchars($valores['classificacao_filme']) ?>"
                    >

                </div>


                <div>

                    <label for="duracao_minutos">
                        Duração (minutos)
                    </label>

                    <input
                        type="number"
                        id="duracao_minutos"
                        name="duracao_minutos"
                        value="<?= htmlspecialchars($valores['duracao_minutos']) ?>"
                        min="1"
                    >

                </div>


                <div>

                    <label for="data_lancamento">
                        Data de lançamento
                    </label>

                    <input
                        type="date"
                        id="data_lancamento"
                        name="data_lancamento"
                        value="<?= htmlspecialchars($valores['data_lancamento']) ?>"
                    >

                </div>

            </div>

        </section>


        <button type="submit">
            Salvar alterações
        </button>

        <a href="produto_individual.php?id=<?= (int) $id ?>" class="back">
            ← Voltar para o produto
        </a>

    </form>

</div>
</main>


<script>

    const categoria = document.getElementById('categoria');

    const camposCategoria = {
        roupa: document.getElementById('fields-roupa'),
        cosmetico: document.getElementById('fields-cosmetico'),
        brinquedo: document.getElementById('fields-brinquedo'),
        jogo: document.getElementById('fields-jogo'),
        filme: document.getElementById('fields-filme')
    };


    function atualizarCamposCategoria() {

        Object.values(camposCategoria).forEach(function (campo) {

            campo.classList.remove('active');

        });


        const categoriaSelecionada = categoria.value;

        if (camposCategoria[categoriaSelecionada]) {

            camposCategoria[categoriaSelecionada]
                .classList.add('active');

        }

    }


    categoria.addEventListener(
        'change',
        atualizarCamposCategoria
    );


    atualizarCamposCategoria();

</script>

</body>
</html>