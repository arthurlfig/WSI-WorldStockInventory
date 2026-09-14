<?php require_once __DIR__ . '/CategoriaProdutoHandler.php';

class BrinquedoHandler extends CategoriaProdutoHandler
{
    protected function validarCamposEspecificos(array $dados): array
    {
        $erros = [];

        if ($dados['classificacao_brinquedo'] === '') {
            $erros[] = 'Informe a classificação indicativa do brinquedo.';
        }

        return $erros;
    }

    protected function inserirDetalhes(int $produtoId, array $dados): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO brinquedos (produto_id, classificacao_indicativa, marca, colecao)
             VALUES (:produto_id, :classificacao_indicativa, :marca, :colecao)'
        );

        $stmt->execute($this->parametros($produtoId, $dados));
    }

    protected function atualizarDetalhes(int $produtoId, array $dados): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE brinquedos SET
                classificacao_indicativa = :classificacao_indicativa,
                marca = :marca,
                colecao = :colecao
             WHERE produto_id = :produto_id'
        );

        $stmt->execute($this->parametros($produtoId, $dados));
    }

    /**
     * @param array<string, mixed> $dados
     * @return array<string, mixed>
     */
    private function parametros(int $produtoId, array $dados): array
    {
        return [
            'produto_id' => $produtoId,
            'classificacao_indicativa' => $dados['classificacao_brinquedo'],
            'marca' => $dados['marca_brinquedo'] !== '' ? $dados['marca_brinquedo'] : null,
            'colecao' => $dados['colecao'] !== '' ? $dados['colecao'] : null,
        ];
    }
}