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

    public function updateThresholds(int $itemId, int $minQuantity, int $desiredQuantity): bool
    {
        $statement = $this->pdo->prepare('UPDATE item SET quantidade_minima = ?, quantidade_desejavel = ? WHERE id_item = ?');
        return $statement->execute([$minQuantity, $desiredQuantity, $itemId]);
    }

    public function findByName(string $name): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM item WHERE item = ? LIMIT 1');
        $statement->execute([$name]);
        $item = $statement->fetch(\PDO::FETCH_ASSOC);
        return $item ?: null;
    }

    public function findWithThresholds(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM item WHERE id_item = ? LIMIT 1');
        $statement->execute([$id]);
        $item = $statement->fetch(\PDO::FETCH_ASSOC);
        return $item ?: null;
    }

    public function addNotificationEmail(int $itemId, string $email): int
    {
        $statement = $this->pdo->prepare('INSERT INTO item_notificacao_email (id_item, email) VALUES (?, ?)');
        $statement->execute([$itemId, $email]);
        return (int)$this->pdo->lastInsertId();
    }

    public function listNotificationEmails(int $itemId): array
    {
        $statement = $this->pdo->prepare('SELECT email FROM item_notificacao_email WHERE id_item = ?');
        $statement->execute([$itemId]);
        return array_map(fn($row) => $row['email'], $statement->fetchAll());
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
