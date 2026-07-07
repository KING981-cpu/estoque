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
        $statement = $this->pdo->prepare(
            'SELECT i.*, COALESCE(m.mov_count, 0) AS movimentacao_count
             FROM item i
             LEFT JOIN (
                 SELECT id_item, COUNT(*) AS mov_count
                 FROM movimentacao
                 GROUP BY id_item
             ) m ON i.id_item = m.id_item
             ORDER BY mov_count DESC, i.item ASC'
        );
        $statement->execute();
        return $statement->fetchAll();
    }

    public function popular(int $limit = 20): array
    {
        $statement = $this->pdo->prepare(
            'SELECT i.*, COALESCE(m.mov_count, 0) AS movimentacao_count
             FROM item i
             LEFT JOIN (
                 SELECT id_item, COUNT(*) AS mov_count
                 FROM movimentacao
                 GROUP BY id_item
             ) m ON i.id_item = m.id_item
             ORDER BY mov_count DESC, i.item ASC
             LIMIT ?'
        );
        $statement->bindValue(1, $limit, 
PDO::PARAM_INT);
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

    public function save(string $name, int $tipoRegistro = 1, string $unidadeMedida = 'un', int $saldoAtual = 0, string $synonyms = '', int $requerComprimento = 0): int
    {
        $statement = $this->pdo->prepare('INSERT INTO item (item, tipo_registro, unidade_medida, saldo_atual, sinonimos, requer_comprimento) VALUES (?, ?, ?, ?, ?, ?)');
        $statement->execute([$name, $tipoRegistro, $unidadeMedida, $saldoAtual, $synonyms, $requerComprimento]);
        return (int)$this->pdo->lastInsertId();
    }

    public function adjustStock(int $itemId, int $delta): bool
    {
        $statement = $this->pdo->prepare('UPDATE item SET saldo_atual = saldo_atual + ? WHERE id_item = ?');
        return $statement->execute([$delta, $itemId]);
    }

    public function updateThresholds(int $itemId, int $minQuantity, int $desiredQuantity, int $purchaseBuffer = 0, string $synonyms = '', string $unidadeMedida = 'un', int $requerComprimento = 0): bool
    {
        $statement = $this->pdo->prepare('UPDATE item SET quantidade_minima = ?, quantidade_desejavel = ?, quantidade_sobra_compra = ?, sinonimos = ?, unidade_medida = ?, requer_comprimento = ? WHERE id_item = ?');
        return $statement->execute([$minQuantity, $desiredQuantity, $purchaseBuffer, $synonyms, $unidadeMedida, $requerComprimento, $itemId]);
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

    public function getOrCreateByName(string $name, int $tipoRegistro = 1, string $synonyms = '', string $unidadeMedida = 'un', int $requerComprimento = 0, int $saldoAtual = 0): int
    {
        $existing = $this->findByName($name);
        if ($existing !== null) {
            return (int)$existing['id_item'];
        }

        return $this->save($name, $tipoRegistro, $unidadeMedida, $saldoAtual, $synonyms, $requerComprimento);
    }

    public function getTipoRegistro(int $id): int
    {
        $statement = $this->pdo->prepare('SELECT tipo_registro FROM item WHERE id_item = ? LIMIT 1');
        $statement->execute([$id]);
        $row = $statement->fetch();
        return isset($row['tipo_registro']) ? (int)$row['tipo_registro'] : 1;
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM item WHERE id_item = ?');
        $statement->execute([$id]);
    }

    public function getStock(int $id): int
    {
        $statement = $this->pdo->prepare('SELECT COALESCE(saldo_atual, 0) AS stock FROM item WHERE id_item = ? LIMIT 1');
        $statement->execute([$id]);
        $row = $statement->fetch();
        return (int)($row['stock'] ?? 0);
    }
}
