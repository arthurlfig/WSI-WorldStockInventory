<?php require_once __DIR__ . '/ValidacaoException.php';

abstract class CategoriaProdutoHandler
{
    protected PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Define a ordem fixa dos passos; quem varia por categoria são os métodos abstratos chamados aqui.
     * @param array<string, mixed> $dados
     * @throws ValidacaoException se algum campo específico for inválido */

    final public function salvar(int $produtoId, array $dados, bool $eEdicao): void
    {
        $erros = $this->validarCamposEspecificos($dados);

        if (!empty($erros)) {
            throw new ValidacaoException($erros);
        }

        if ($eEdicao) {
            $this->atualizarDetalhes($produtoId, $dados);
        } else {
            $this->inserirDetalhes($produtoId, $dados);
        }
    }

    /** Valida os campos específicos da categoria. Retorna um array de mensagens de erro (vazio = tudo certo).
     * @param array<string, mixed> $dados
     * @return string[] */
    
    abstract protected function validarCamposEspecificos(array $dados): array;

    /** @param array<string, mixed> $dados */

    abstract protected function inserirDetalhes(int $produtoId, array $dados): void;

    /** Atualiza a linha de detalhes de um produto já existente.
     * @param array<string, mixed> $dados */

    abstract protected function atualizarDetalhes(int $produtoId, array $dados): void;
}