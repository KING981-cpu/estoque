<?php

require_once __DIR__ . '/Database.php';

class ItemModel
{
    private \PDO $pdo;

    public function __construct(Database $database)
    {
        $this->pdo = $database->pdo();
    }

    public function all(): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM item ORDER BY item ASC');
        $statement->execute();
        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM item WHERE id_item = ?');
        $statement->execute([$id]);
        $item = $statement->fetch();
        return $item ?: null;
    }

    public function save(string $name): int
    {
        $statement = $this->pdo->prepare('INSERT INTO item (item) VALUES (?)');
        $statement->execute([$name]);
        return (int)$this->pdo->lastInsertId();
    }

    public function findByName(string $name): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM item WHERE item = ? LIMIT 1');
        $statement->execute([$name]);
        $item = $statement->fetch(\PDO::FETCH_ASSOC);
        return $item ?: null;
    }

    public function getOrCreateByName(string $name): int
    {
        $existing = $this->findByName($name);
        if ($existing !== null) {
            return (int)$existing['id_item'];
        }

        return $this->save($name);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM item WHERE id_item = ?');
        $statement->execute([$id]);
    }

    public function getStock(int $id): int
    {
        $statement = $this->pdo->prepare("SELECT COALESCE(SUM(CASE WHEN tipo = 'entrada' THEN quantidade ELSE -quantidade END), 0) AS stock FROM movimentacao WHERE id_item = ?");
        $statement->execute([$id]);
        $row = $statement->fetch();
        return (int)($row['stock'] ?? 0);
    }
}
