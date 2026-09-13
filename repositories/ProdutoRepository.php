<?php
class ProdutoRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listarPorCategoria(?string $categoria = null): array
    {
        $sql = '
            SELECT
                p.id,
                p.nome,
                p.descricao,
                p.categoria,
                p.codigo_barras,
                p.preco_venda,
                p.estoque_minimo,

                f.nome AS fornecedor_nome,

                r.tamanho,
                r.marca AS roupa_marca,
                r.sexo,

                c.categoria_cosmetico,
                c.tom_cor,
                c.quantidade_valor,
                c.quantidade_unidade,

                b.classificacao_indicativa AS classificacao_brinquedo,
                b.marca AS brinquedo_marca,
                b.colecao,

                j.genero AS genero_jogo,
                j.classificacao_indicativa AS classificacao_jogo,
                j.desenvolvedora,
                j.plataforma,
                j.modo_jogo,

                fi.genero AS genero_filme,
                fi.classificacao_indicativa AS classificacao_filme,
                fi.duracao_minutos,
                fi.data_lancamento

            FROM produtos p

            LEFT JOIN fornecedores f
                ON f.id = p.fornecedor_id

            LEFT JOIN roupas r
                ON r.produto_id = p.id

            LEFT JOIN cosmeticos c
                ON c.produto_id = p.id

            LEFT JOIN brinquedos b
                ON b.produto_id = p.id

            LEFT JOIN jogos j
                ON j.produto_id = p.id

            LEFT JOIN filmes fi
                ON fi.produto_id = p.id
        ';

        $params = [];

        $sql .= ' WHERE p.ativo = 1';

        if ($categoria !== null && $categoria !== '') {
            $sql .= ' AND p.categoria = :categoria';
            $params['categoria'] = $categoria;
        }

        $sql .= ' ORDER BY p.nome';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca um produto pelo id, já com os campos específicos da
     * categoria (roupa, cosmético, brinquedo, jogo ou filme) juntados
     * na mesma linha. Usado pela tela de edição pra pré-preencher o
     * formulário. Retorna null se o produto não existir.
     */
    public function buscarPorId(int $id): ?array
    {
        $sql = '
            SELECT
                p.id,
                p.nome,
                p.descricao,
                p.categoria,
                p.codigo_barras,
                p.preco_venda,
                p.fornecedor_id,
                p.estoque_minimo,
                p.ativo,

                r.tamanho,
                r.marca AS marca_roupa,
                r.sexo,

                c.categoria_cosmetico,
                c.tom_cor,
                c.quantidade_valor,
                c.quantidade_unidade,
                c.modo_aplicacao,
                c.registro_anvisa,

                b.classificacao_indicativa AS classificacao_brinquedo,
                b.marca AS marca_brinquedo,
                b.colecao,

                j.genero AS genero_jogo,
                j.classificacao_indicativa AS classificacao_jogo,
                j.desenvolvedora,
                j.plataforma,
                j.modo_jogo,

                fi.genero AS genero_filme,
                fi.classificacao_indicativa AS classificacao_filme,
                fi.duracao_minutos,
                fi.data_lancamento

            FROM produtos p

            LEFT JOIN roupas r      ON r.produto_id = p.id
            LEFT JOIN cosmeticos c  ON c.produto_id = p.id
            LEFT JOIN brinquedos b  ON b.produto_id = p.id
            LEFT JOIN jogos j       ON j.produto_id = p.id
            LEFT JOIN filmes fi     ON fi.produto_id = p.id

            WHERE p.id = :id
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $produto = $stmt->fetch(PDO::FETCH_ASSOC);

        return $produto !== false ? $produto : null;
    }

    /**
     * Inativa (soft delete) um produto — ele some das listagens e não
     * pode mais receber novas movimentações, mas todo o histórico de
     * lotes, entradas/saídas e vendas é preservado.
     */
    public function inativar(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE produtos SET ativo = 0 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * Reativa um produto previamente inativado.
     */
    public function reativar(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE produtos SET ativo = 1 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

}