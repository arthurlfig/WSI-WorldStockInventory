<?php require_once __DIR__ . '/CategoriaProdutoHandler.php';

class CosmeticoHandler extends CategoriaProdutoHandler
{
    protected function validarCamposEspecificos(array $dados): array
    {
        $erros = [];

        $categoriasValidas = ['perfume', 'maquiagem', 'skincare', 'capilar', 'cuidados_pessoais'];

        if (!in_array($dados['categoria_cosmetico'], $categoriasValidas, true)) {
            $erros[] = 'Selecione uma categoria de cosmético válida.';
        }

        $unidadesValidas = ['ml', 'g', 'un'];

        if (!in_array($dados['quantidade_unidade'], $unidadesValidas, true)) {
            $erros[] = 'Selecione uma unidade válida.';
        }

        if (
            $dados['quantidade_valor'] !== '' &&
            (!is_numeric($dados['quantidade_valor']) || $dados['quantidade_valor'] < 0)
        ) {
            $erros[] = 'Informe uma quantidade válida para o cosmético.';
        }

        return $erros;
    }

    protected function inserirDetalhes(int $produtoId, array $dados): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO cosmeticos
                (produto_id, categoria_cosmetico, tom_cor, quantidade_valor, quantidade_unidade, modo_aplicacao, registro_anvisa)
             VALUES
                (:produto_id, :categoria_cosmetico, :tom_cor, :quantidade_valor, :quantidade_unidade, :modo_aplicacao, :registro_anvisa)'
        );

        $stmt->execute($this->parametros($produtoId, $dados));
    }

    protected function atualizarDetalhes(int $produtoId, array $dados): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE cosmeticos SET
                categoria_cosmetico = :categoria_cosmetico,
                tom_cor = :tom_cor,
                quantidade_valor = :quantidade_valor,
                quantidade_unidade = :quantidade_unidade,
                modo_aplicacao = :modo_aplicacao,
                registro_anvisa = :registro_anvisa
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
            'categoria_cosmetico' => $dados['categoria_cosmetico'],
            'tom_cor' => $dados['tom_cor'] !== '' ? $dados['tom_cor'] : null,
            'quantidade_valor' => $dados['quantidade_valor'] !== '' ? $dados['quantidade_valor'] : null,
            'quantidade_unidade' => $dados['quantidade_unidade'],
            'modo_aplicacao' => $dados['modo_aplicacao'] !== '' ? $dados['modo_aplicacao'] : null,
            'registro_anvisa' => $dados['registro_anvisa'] !== '' ? $dados['registro_anvisa'] : null,
        ];
    }
}