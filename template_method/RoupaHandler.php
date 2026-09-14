<?php require_once __DIR__ . '/CategoriaProdutoHandler.php';

class RoupaHandler extends CategoriaProdutoHandler
{
    protected function validarCamposEspecificos(array $dados): array
    {
        $erros = [];

        if ($dados['tamanho'] === '') {
            $erros[] = 'Informe o tamanho da roupa.';
        }

        $sexosValidos = ['masculino', 'feminino', 'unissex'];

        if (!in_array($dados['sexo'], $sexosValidos, true)) {
            $erros[] = 'Selecione um sexo válido para a roupa.';
        }

        return $erros;
    }

    protected function inserirDetalhes(int $produtoId, array $dados): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO roupas (produto_id, tamanho, marca, sexo)
             VALUES (:produto_id, :tamanho, :marca, :sexo)'
        );

        $stmt->execute([
            'produto_id' => $produtoId,
            'tamanho' => $dados['tamanho'],
            'marca' => $dados['marca_roupa'] !== '' ? $dados['marca_roupa'] : null,
            'sexo' => $dados['sexo'],
        ]);
    }

    protected function atualizarDetalhes(int $produtoId, array $dados): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE roupas SET
                tamanho = :tamanho,
                marca = :marca,
                sexo = :sexo
             WHERE produto_id = :produto_id'
        );

        $stmt->execute([
            'produto_id' => $produtoId,
            'tamanho' => $dados['tamanho'],
            'marca' => $dados['marca_roupa'] !== '' ? $dados['marca_roupa'] : null,
            'sexo' => $dados['sexo'],
        ]);
    }
}