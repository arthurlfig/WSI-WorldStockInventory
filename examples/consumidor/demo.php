<?php
/**
 * Demo de reuso: um "outro projeto" que usa o pacote wsi/core apenas pelo .phar,
 * sem ter acesso ao codigo-fonte do WorldStockInventory.
 *
 * Uso:
 *     php demo.php                       (procura wsi-core.phar ao lado deste arquivo
 *                                         ou em ../../dist/)
 *     php demo.php caminho/do/arquivo.phar
 *
 * Requisitos: MySQL ligado e banco "wsi" criado (sql/schemas.sql). Se o seu banco usa
 * outro usuario/senha, defina DB_USER e DB_PASS antes de rodar.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Execute este script apenas pela linha de comando.');
}

// ------------------------------------------------------------ localiza o .phar
$raiz = dirname(__DIR__, 2);
$versao = is_file($raiz . '/VERSION') ? trim(file_get_contents($raiz . '/VERSION')) : '1.0.0';

$candidatos = array_filter([
    $argv[1] ?? null,
    __DIR__ . '/wsi-core.phar',
    __DIR__ . '/wsi-core-' . $versao . '.phar',
    $raiz . '/dist/wsi-core-' . $versao . '.phar',
]);

$phar = null;
foreach ($candidatos as $candidato) {
    if (is_file($candidato)) {
        $phar = $candidato;
        break;
    }
}

if ($phar === null) {
    fwrite(STDERR, "ERRO: .phar nao encontrado. Gere com: php -d phar.readonly=0 build/build-phar.php\n");
    exit(1);
}

echo "Usando o pacote: " . realpath($phar) . PHP_EOL . PHP_EOL;

// ------------------------------------------------- 1) um unico require basta
require $phar;

echo "[1] Classes do pacote disponiveis via autoload:" . PHP_EOL;
foreach ([
    'Database', 'ProdutoRepository', 'UsuarioRepository', 'FornecedorRepository',
    'CategoriaProdutoHandler', 'RoupaHandler', 'CosmeticoHandler', 'BrinquedoHandler',
    'ValidacaoException',
] as $classe) {
    echo sprintf("    %-26s %s", $classe, class_exists($classe) ? 'OK' : 'NAO ENCONTRADA') . PHP_EOL;
}

// ------------------------------------------------- 2) Singleton reaproveitado
echo PHP_EOL . "[2] Singleton (Database):" . PHP_EOL;
$pdo = Database::getConnection();
$mesmaInstancia = ($pdo === Database::getConnection());
echo "    Duas chamadas a getConnection() devolvem o mesmo objeto? " . ($mesmaInstancia ? 'SIM' : 'NAO') . PHP_EOL;

// ------------------------------------------------ 3) Repository reaproveitado
echo PHP_EOL . "[3] Repository (FornecedorRepository):" . PHP_EOL;
$fornecedores = (new FornecedorRepository($pdo))->listar();
echo "    Fornecedores cadastrados: " . count($fornecedores) . PHP_EOL;
foreach (array_slice($fornecedores, 0, 5) as $f) {
    echo "      - " . $f['nome'] . ($f['pais_origem'] ? ' (' . $f['pais_origem'] . ')' : '') . PHP_EOL;
}

// --------------------------------------- 4) Template Method reaproveitado
echo PHP_EOL . "[4] Template Method (RoupaHandler com dados invalidos):" . PHP_EOL;
$dadosInvalidos = ['tamanho' => '', 'marca_roupa' => '', 'sexo' => 'qualquer'];
try {
    (new RoupaHandler($pdo))->salvar(0, $dadosInvalidos, false);
    echo "    (nao deveria chegar aqui)" . PHP_EOL;
} catch (ValidacaoException $e) {
    echo "    Validacao falhou antes de tocar no banco, erros devolvidos:" . PHP_EOL;
    foreach ($e->getErros() as $erro) {
        echo "      - " . $erro . PHP_EOL;
    }
}

echo PHP_EOL . "Fim da demo." . PHP_EOL;
