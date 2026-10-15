# WorldStockInventory (WSI)

Sistema de controle de estoque em PHP/MySQL (cadastro de produtos por categoria, fornecedores,
lotes e validade, entradas, saidas, vendas e historico).

Alem do sistema web, a camada reutilizavel do projeto esta empacotada como **`wsi/core`**:
um artefato unico (`.phar`) e um pacote Composer, para que outros projetos usem as mesmas
classes sem copiar e colar codigo.

## O que tem no pacote `wsi/core`

| Classe | Padrao de projeto | Para que serve |
|---|---|---|
| `Database` | Singleton | Conexao PDO/MySQL unica, configurada por variaveis de ambiente |
| `ProdutoRepository` | Repository | Leitura de produtos (com dados da categoria), inativar e reativar |
| `UsuarioRepository` | Repository | Consulta e manutencao de contas de usuario |
| `FornecedorRepository` | Repository | CRUD de fornecedores e checagem de duplicidade |
| `CategoriaProdutoHandler` | Template Method | Algoritmo "valida -> insere ou atualiza" dos dados especificos de uma categoria |
| `RoupaHandler`, `CosmeticoHandler`, `BrinquedoHandler` | Template Method | Passos especificos de cada categoria |
| `ValidacaoException` | - | Erros de validacao levantados pelos handlers |

Ficaram **fora** do pacote de proposito: `auth.php` (depende de sessao e gera HTML da aplicacao)
e as telas `.php` (sao a aplicacao, nao uma biblioteca).

## Requisitos

- PHP 8.0 ou superior (testado no PHP 8.3) com as extensoes `pdo` e `pdo_mysql`
- MySQL/MariaDB com o banco criado a partir de `sql/schemas.sql`
- Para ler o `.phar`: extensoes `phar` e `zlib` (ja vem habilitadas no XAMPP)

## Configuracao do banco

A conexao le estas variaveis de ambiente; se nao existirem, usa os valores padrao:

| Variavel | Padrao |
|---|---|
| `DB_HOST` | `127.0.0.1` |
| `DB_PORT` | `3306` |
| `DB_NAME` | `wsi` |
| `DB_USER` | `root` |
| `DB_PASS` | *(vazio)* |

## Gerando o artefato (.phar)

Na raiz do projeto:

```bash
php -d phar.readonly=0 build/build-phar.php
# ou, com o Composer instalado:
composer build
```

Saida: `dist/wsi-core-<versao>.phar` (gzip + assinatura SHA-256) e `dist/wsi-core-<versao>.phar.sha256`.
A versao vem do arquivo `VERSION`. Para ver o conteudo do pacote:

```bash
php dist/wsi-core-1.0.0.phar
```

## Usando em outro projeto

### 1. Pelo .phar (um unico `require`)

```php
<?php
require __DIR__ . '/wsi-core-1.0.0.phar';

$pdo = Database::getConnection();                 // Singleton
$fornecedores = (new FornecedorRepository($pdo))->listar();

try {
    (new RoupaHandler($pdo))->salvar($produtoId, $dados, false);   // Template Method
} catch (ValidacaoException $e) {
    print_r($e->getErros());
}
```

Um exemplo completo e executavel esta em `examples/consumidor/demo.php`.

### 2. Pelo Composer

O `composer.json` declara o pacote (`wsi/core`), a licenca e o autoload por classmap, entao o
projeto tambem pode ser consumido como dependencia. Com o repositorio publicado no GitHub e a
tag `v1.0.0` criada:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/<usuario>/WorldStockInventory" }
    ],
    "require": { "wsi/core": "^1.0" }
}
```

Para testar localmente, sem GitHub, use um repositorio do tipo `path` apontando para a pasta
do projeto:

```json
{
    "repositories": [
        { "type": "path", "url": "../WorldStockInventory" }
    ],
    "require": { "wsi/core": "*@dev" }
}
```

Depois `composer install` e `require 'vendor/autoload.php';`.

## Versionamento

O projeto segue [SemVer](https://semver.org/lang/pt-BR/). O historico de mudancas esta em
[`CHANGELOG.md`](CHANGELOG.md). Cada versao publicada corresponde a uma tag do git (`v1.0.0`)
e ao `.phar` anexado ao Release.

## Verificando a integridade

O `.phar` e assinado (SHA-256) e o PHP confere a assinatura ao carrega-lo. Alem disso, o
arquivo `.sha256` publicado junto permite conferir o download:

```bash
# Linux/macOS
sha256sum -c wsi-core-1.0.0.phar.sha256
# Windows (PowerShell)
Get-FileHash wsi-core-1.0.0.phar -Algorithm SHA256
```

## Licenca

[MIT](LICENSE)
