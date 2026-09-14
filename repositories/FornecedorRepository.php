<?php

class FornecedorRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listar(string $busca = '', string $filtroStatus = 'todos'): array
    {
        $sql = 'SELECT id, nome, cnpj_cpf, pais_origem, telefone, email, contato_responsavel, ativo, criado_em
                FROM fornecedores';

        $condicoes = [];
        $parametros = [];

        if ($busca !== '') {
            $condicoes[] = '(nome LIKE :busca1 OR pais_origem LIKE :busca2 OR contato_responsavel LIKE :busca3)';
            $valorBusca = '%' . $busca . '%';
            $parametros['busca1'] = $valorBusca;
            $parametros['busca2'] = $valorBusca;
            $parametros['busca3'] = $valorBusca;
        }

        if ($filtroStatus === 'ativos') {
            $condicoes[] = 'ativo = 1';
        } elseif ($filtroStatus === 'inativos') {
            $condicoes[] = 'ativo = 0';
        }

        if (!empty($condicoes)) {
            $sql .= ' WHERE ' . implode(' AND ', $condicoes);
        }

        $sql .= ' ORDER BY nome';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parametros);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM fornecedores WHERE id = :id');
        $stmt->execute(['id' => $id]);

        $fornecedor = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fornecedor !== false ? $fornecedor : null;
    }

    /* Verifica se já existe outro fornecedor com o mesmo nome ou o mesmo CNPJ/CPF, ignorando */

    public function existeDuplicado(string $nome, string $cnpjCpf, ?int $idAtual = null): bool
    {
        $condicoes = ['LOWER(nome) = LOWER(:nome)'];
        $parametros = ['nome' => $nome];

        if ($cnpjCpf !== '') {
            $condicoes[] = 'cnpj_cpf = :cnpj_cpf';
            $parametros['cnpj_cpf'] = $cnpjCpf;
        }

        $sql = 'SELECT id FROM fornecedores WHERE (' . implode(' OR ', $condicoes) . ')';

        if ($idAtual !== null) {
            $sql .= ' AND id != :id_atual';
            $parametros['id_atual'] = $idAtual;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parametros);

        return (bool) $stmt->fetch();
    }

    public function inserir(array $dados): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO fornecedores
                (nome, cnpj_cpf, pais_origem, telefone, email, endereco, contato_responsavel)
             VALUES
                (:nome, :cnpj_cpf, :pais_origem, :telefone, :email, :endereco, :contato_responsavel)'
        );

        $stmt->execute($this->parametros($dados));
    }

    public function atualizar(int $id, array $dados): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE fornecedores SET
                nome = :nome,
                cnpj_cpf = :cnpj_cpf,
                pais_origem = :pais_origem,
                telefone = :telefone,
                email = :email,
                endereco = :endereco,
                contato_responsavel = :contato_responsavel
             WHERE id = :id'
        );

        $stmt->execute($this->parametros($dados) + ['id' => $id]);
    }

    public function alternarStatus(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE fornecedores SET ativo = NOT ativo WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * @param array<string, mixed> $dados
     * @return array<string, mixed>
     */
    private function parametros(array $dados): array
    {
        return [
            'nome' => $dados['nome'],
            'cnpj_cpf' => $dados['cnpj_cpf'] !== '' ? $dados['cnpj_cpf'] : null,
            'pais_origem' => $dados['pais_origem'] !== '' ? $dados['pais_origem'] : null,
            'telefone' => $dados['telefone'] !== '' ? $dados['telefone'] : null,
            'email' => $dados['email'] !== '' ? $dados['email'] : null,
            'endereco' => $dados['endereco'] !== '' ? $dados['endereco'] : null,
            'contato_responsavel' => $dados['contato_responsavel'] !== '' ? $dados['contato_responsavel'] : null,
        ];
    }
}