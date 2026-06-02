<?php

require_once __DIR__ . '/Database.php';

class FuncionarioModel
{
    private \PDO $pdo;

    public function __construct(Database $database)
    {
        $this->pdo = $database->pdo();
    }

    public function all(): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM funcionario ORDER BY nome ASC');
        $statement->execute();
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM funcionario WHERE id_funcionario = ?');
        $statement->execute([$id]);
        $funcionario = $statement->fetch(\PDO::FETCH_ASSOC);
        return $funcionario ?: null;
    }

    public function save(string $nome): int
    {
        $statement = $this->pdo->prepare('INSERT INTO funcionario (nome) VALUES (?)');
        $statement->execute([$nome]);
        return (int)$this->pdo->lastInsertId();
    }
}
