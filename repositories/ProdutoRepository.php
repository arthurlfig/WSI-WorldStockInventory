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

        if ($categoria !== null && $categoria !== '') {
            $sql .= ' WHERE p.categoria = :categoria';
            $params['categoria'] = $categoria;
        }

        $sql .= ' ORDER BY p.nome';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
}