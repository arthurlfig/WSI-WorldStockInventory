<?php

class UsuarioRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listar(
        string $busca = '',
        string $filtroStatus = 'todos',
        string $filtroNivel = 'todos'
    ): array {
        $sql = '
            SELECT 
                id,
                nome,
                email,
                nivel_acesso,
                ativo,
                criado_em
            FROM usuarios
        ';

        $condicoes = [];
        $parametros = [];

        
        if ($busca !== '') {
            $condicoes[] = '(nome LIKE :busca_nome OR email LIKE :busca_email)';
            $parametros['busca_nome'] = '%' . $busca . '%';
            $parametros['busca_email'] = '%' . $busca . '%';
        }

        
        if ($filtroStatus === 'ativos') {
            $condicoes[] = 'ativo = 1';
        } elseif ($filtroStatus === 'inativos') {
            $condicoes[] = 'ativo = 0';
        }

       
        if (in_array($filtroNivel, ['admin', 'operador'], true)) {
            $condicoes[] = 'nivel_acesso = :nivel_filtro';
            $parametros['nivel_filtro'] = $filtroNivel;
        }

       
        if (!empty($condicoes)) {
            $sql .= ' WHERE ' . implode(' AND ', $condicoes);
        }

        $sql .= ' ORDER BY nome';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parametros);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarTotal(): int
    {
        $stmt = $this->pdo->query(
            'SELECT COUNT(*) FROM usuarios'
        );

        return (int) $stmt->fetchColumn();
    }

    public function contarAtivas(): int
    {
        $stmt = $this->pdo->query(
            'SELECT COUNT(*) FROM usuarios WHERE ativo = 1'
        );

        return (int) $stmt->fetchColumn();
    }

    public function contarAdmins(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) FROM usuarios WHERE nivel_acesso = 'admin'"
        );

        return (int) $stmt->fetchColumn();
    }
}