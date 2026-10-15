<?php
/**
 * Gera o artefato distribuivel dist/wsi-core-<versao>.phar
 *
 * Uso (na raiz do projeto):
 *     php -d phar.readonly=0 build/build-phar.php
 * ou, se o Composer estiver instalado:
 *     composer build
 *
 * O que o script faz:
 *   1. le a versao do arquivo VERSION e os metadados do composer.json;
 *   2. reune as classes reutilizaveis (config/database.php, repositories/,
 *      template_method/) mais README, CHANGELOG e LICENSE;
 *   3. gera um mapa classe -> arquivo e embute no stub um autoloader, de modo que
 *      basta um unico `require` do .phar para usar as classes;
 *   4. assina o pacote (SHA-256), comprime os arquivos (GZ) e grava o checksum.
 *
 * As mensagens de saida usam so ASCII para nao quebrar no terminal do Windows.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Execute este script apenas pela linha de comando.');
}

if (!extension_loaded('phar')) {
    fwrite(STDERR, "ERRO: a extensao phar nao esta habilitada neste PHP.\n");
    exit(1);
}

if (ini_get('phar.readonly')) {
    fwrite(STDERR, "ERRO: o PHP esta com phar.readonly ligado, entao nao consegue criar .phar.\n");
    fwrite(STDERR, "Rode assim:  php -d phar.readonly=0 build/build-phar.php\n");
    exit(1);
}

$raiz = dirname(__DIR__);

// ---------------------------------------------------------------- metadados
$versao = trim((string) @file_get_contents($raiz . '/VERSION'));
if (!preg_match('/^\d+\.\d+\.\d+$/', $versao)) {
    fwrite(STDERR, "ERRO: o arquivo VERSION precisa conter uma versao no formato X.Y.Z (ex.: 1.0.0).\n");
    exit(1);
}

$composer = json_decode((string) @file_get_contents($raiz . '/composer.json'), true);
if (!is_array($composer)) {
    fwrite(STDERR, "ERRO: nao foi possivel ler o composer.json.\n");
    exit(1);
}

$nomePacote = $composer['name'] ?? 'wsi/core';
$licenca = $composer['license'] ?? 'MIT';

// ------------------------------------------------------- arquivos do pacote
$codigo = ['config/config.php', 'config/database.php'];
foreach (['repositories', 'template_method'] as $pasta) {
    $encontrados = glob($raiz . '/' . $pasta . '/*.php') ?: [];
    sort($encontrados);
    foreach ($encontrados as $caminho) {
        $codigo[] = $pasta . '/' . basename($caminho);
    }
}

$documentacao = ['README.md', 'CHANGELOG.md', 'LICENSE', 'VERSION', 'composer.json'];

foreach (array_merge($codigo, $documentacao) as $relativo) {
    if (!is_file($raiz . '/' . $relativo)) {
        fwrite(STDERR, "ERRO: arquivo esperado nao encontrado: {$relativo}\n");
        exit(1);
    }
}

// ----------------------------------------------- mapa classe -> arquivo
/**
 * Descobre as classes declaradas em cada arquivo usando o tokenizador do PHP
 * (assim, a palavra "class" dentro de comentarios nao conta).
 *
 * @return string[] nomes das classes/interfaces/traits do arquivo
 */
function classesDoArquivo(string $caminho): array
{
    $tokens = token_get_all((string) file_get_contents($caminho));
    $classes = [];
    $total = count($tokens);

    for ($i = 0; $i < $total; $i++) {
        $token = $tokens[$i];

        if (!is_array($token) || !in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT], true)) {
            continue;
        }

        // ignora "Foo::class"
        $anterior = $i - 1;
        while ($anterior >= 0 && is_array($tokens[$anterior]) && $tokens[$anterior][0] === T_WHITESPACE) {
            $anterior--;
        }
        if ($anterior >= 0 && is_array($tokens[$anterior]) && $tokens[$anterior][0] === T_DOUBLE_COLON) {
            continue;
        }

        for ($j = $i + 1; $j < $total; $j++) {
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                $classes[] = $tokens[$j][1];
                break;
            }
        }
    }

    return $classes;
}

$mapaClasses = [];
foreach ($codigo as $relativo) {
    foreach (classesDoArquivo($raiz . '/' . $relativo) as $classe) {
        $mapaClasses[$classe] = $relativo;
    }
}
ksort($mapaClasses);

// -------------------------------------------------------------------- stub
$mapaExportado = var_export($mapaClasses, true);

$stub = <<<STUB
<?php
/**
 * {$nomePacote} {$versao} - artefato distribuivel do WorldStockInventory.
 *
 * Para usar em outro projeto:
 *     require '/caminho/para/wsi-core-{$versao}.phar';
 *     \$pdo = Database::getConnection();
 *
 * Para ver as informacoes do pacote:
 *     php wsi-core-{$versao}.phar
 */
Phar::mapPhar('wsi-core.phar');

spl_autoload_register(static function (string \$classe): void {
    static \$mapa = {$mapaExportado};

    if (isset(\$mapa[\$classe])) {
        require 'phar://wsi-core.phar/' . \$mapa[\$classe];
    }
});

// Executado direto na linha de comando (php arquivo.phar): mostra o conteudo do pacote.
if (PHP_SAPI === 'cli' && isset(\$_SERVER['argv'][0]) && realpath(\$_SERVER['argv'][0]) === realpath(__FILE__)) {
    \$phar = new Phar(__FILE__);
    \$meta = \$phar->getMetadata();

    echo "Pacote   : " . \$meta['name'] . PHP_EOL;
    echo "Versao   : " . \$meta['version'] . PHP_EOL;
    echo "Licenca  : " . \$meta['license'] . PHP_EOL;
    echo "Gerado em: " . \$meta['built_at'] . PHP_EOL;
    echo "PHP      : " . \$meta['php'] . PHP_EOL . PHP_EOL;

    echo "Classes disponiveis (carregadas sob demanda pelo autoloader):" . PHP_EOL;
    foreach (\$meta['classes'] as \$classe => \$arquivo) {
        echo sprintf("  - %-26s %s", \$classe, \$arquivo) . PHP_EOL;
    }

    echo PHP_EOL . "Arquivos dentro do .phar:" . PHP_EOL;
    \$prefixo = 'phar://' . __FILE__ . '/';
    foreach (new RecursiveIteratorIterator(\$phar) as \$arquivo) {
        echo "  " . str_replace(\$prefixo, '', \$arquivo->getPathname()) . PHP_EOL;
    }
    exit(0);
}

__HALT_COMPILER();
STUB;

// ------------------------------------------------------------- construcao
$destino = $raiz . '/dist';
if (!is_dir($destino) && !mkdir($destino, 0775, true) && !is_dir($destino)) {
    fwrite(STDERR, "ERRO: nao foi possivel criar a pasta dist/.\n");
    exit(1);
}

$arquivoPhar = $destino . '/wsi-core-' . $versao . '.phar';
foreach ([$arquivoPhar, $arquivoPhar . '.sha256'] as $antigo) {
    if (file_exists($antigo)) {
        unlink($antigo);
    }
}

echo "Construindo {$nomePacote} {$versao} ..." . PHP_EOL;

$phar = new Phar($arquivoPhar, 0, 'wsi-core.phar');
$phar->startBuffering();

$bytesOriginais = 0;
foreach (array_merge($codigo, $documentacao) as $relativo) {
    $phar->addFile($raiz . '/' . $relativo, $relativo);
    $bytesOriginais += filesize($raiz . '/' . $relativo);
    echo "  + {$relativo}" . PHP_EOL;
}

$phar->setMetadata([
    'name' => $nomePacote,
    'version' => $versao,
    'license' => $licenca,
    'built_at' => gmdate('Y-m-d H:i:s') . ' UTC',
    'php' => PHP_VERSION,
    'classes' => $mapaClasses,
]);
$phar->setStub($stub);
$phar->setSignatureAlgorithm(Phar::SHA256);
$phar->stopBuffering();

if (extension_loaded('zlib')) {
    $phar->compressFiles(Phar::GZ);
    $compactado = true;
} else {
    $compactado = false;
    echo "AVISO: extensao zlib ausente, os arquivos ficaram sem compressao." . PHP_EOL;
}

unset($phar);
clearstatcache();

// --------------------------------------------------------------- checksum
$hash = hash_file('sha256', $arquivoPhar);
file_put_contents($arquivoPhar . '.sha256', $hash . '  ' . basename($arquivoPhar) . PHP_EOL);

$bytesFinais = filesize($arquivoPhar);

echo PHP_EOL . "Pronto!" . PHP_EOL;
echo "  Artefato : dist/" . basename($arquivoPhar) . PHP_EOL;
echo "  Arquivos : " . count($codigo) . " de codigo + " . count($documentacao) . " de documentacao" . PHP_EOL;
echo "  Classes  : " . count($mapaClasses) . PHP_EOL;
echo "  Tamanho  : " . $bytesOriginais . " bytes de fonte -> " . $bytesFinais . " bytes no .phar"
    . ($compactado ? " (gzip)" : "") . PHP_EOL;
echo "  SHA-256  : " . $hash . PHP_EOL;
