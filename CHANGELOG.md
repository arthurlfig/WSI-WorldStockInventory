# Changelog

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/).
O versionamento segue [SemVer](https://semver.org/lang/pt-BR/).

## [1.0.0] - 2026-10-13

### Adicionado
- Primeiro empacotamento da camada reutilizavel do WorldStockInventory (`wsi/core`).
- `Database`: conexao PDO/MySQL em Singleton, configurada por variaveis de ambiente
  (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`).
- Repositorios: `ProdutoRepository`, `UsuarioRepository` e `FornecedorRepository`.
- Template Method: `CategoriaProdutoHandler` (abstrata) com `RoupaHandler`,
  `CosmeticoHandler` e `BrinquedoHandler`, alem de `ValidacaoException`.
- Script `build/build-phar.php` que gera `dist/wsi-core-<versao>.phar` (compactado,
  assinado com SHA-256) e o arquivo de checksum `.sha256`.
- Demo de consumo em `examples/consumidor/demo.php`.
