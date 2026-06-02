<?php

require_once __DIR__ . '/Database.php';

class LocalidadeModel
{
    private \PDO $pdo;

    public function __construct(Database $database)
    {
        $this->pdo = $database->pdo();
    }

    public function all(): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM localidade ORDER BY local ASC');
        $statement->execute();
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM localidade WHERE id_localidade = ?');
        $statement->execute([$id]);
        $localidade = $statement->fetch(\PDO::FETCH_ASSOC);
        return $localidade ?: null;
    }

    public function save(string $local): int
    {
        $statement = $this->pdo->prepare('INSERT INTO localidade (local) VALUES (?)');
        $statement->execute([$local]);
        return (int)$this->pdo->lastInsertId();
    }

    public function findByName(string $local): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM localidade WHERE local = ? LIMIT 1');
        $statement->execute([$local]);
        $localidade = $statement->fetch(\PDO::FETCH_ASSOC);
        return $localidade ?: null;
    }

    public function ensureExists(string $local): int
    {
        $existing = $this->findByName($local);
        if ($existing !== null) {
            return (int)$existing['id_localidade'];
        }

        return $this->save($local);
    }
}
