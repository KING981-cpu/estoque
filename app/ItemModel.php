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

    private array $itemColumnCache = [];

    private function hasItemColumn(string $column): bool
    {
        if (array_key_exists($column, $this->itemColumnCache)) {
            return $this->itemColumnCache[$column];
        }

        try {
            $statement = $this->pdo->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "item" AND COLUMN_NAME = ?'
            );
            $statement->execute([$column]);
            $this->itemColumnCache[$column] = (int)$statement->fetchColumn() > 0;
        } catch (\PDOException $exception) {
            $this->itemColumnCache[$column] = false;
        }

        return $this->itemColumnCache[$column];
    }

    private function hasCondicaoColumn(): bool
    {
        return $this->hasItemColumn('condicao');
    }

    private function hasCategoriaColumn(): bool
    {
        return $this->hasItemColumn('categoria');
    }

    private function hasTipoItemColumn(): bool
    {
        return $this->hasItemColumn('tipo_item');
    }

    private function normalizeTipoItem(string $tipoItem): string
    {
        $normalized = strtolower(trim($tipoItem));
        return $normalized === 'permanente' ? 'permanente' : 'temporario';
    }

    private function resolveLegacyTipoRegistro(int $tipoRegistro, string $tipoItem): int
    {
        $normalizedTipoItem = $this->normalizeTipoItem($tipoItem);
        if ($normalizedTipoItem === 'permanente') {
            return 1;
        }
        if ($normalizedTipoItem === 'temporario') {
            return 0;
        }
        return $tipoRegistro === 0 ? 0 : 1;
    }

    public function save(string $category, string $name, string $condition = 'novo', int $tipoRegistro = 1, string $unidadeMedida = 'un', int $saldoAtual = 0, string $synonyms = '', int $requerComprimento = 0, string $tipoItem = 'temporario'): int
    {
        $columns = ['item'];
        $params = [$name];

        if ($this->hasCategoriaColumn()) {
            $categoryValue = trim($category);
            if ($categoryValue === '') {
                $categoryValue = 'Sem categoria';
            }
            array_unshift($columns, 'categoria');
            array_unshift($params, $categoryValue);
        }

        if ($this->hasCondicaoColumn()) {
            $columns[] = 'condicao';
            $params[] = $condition;
        }

        $tipoItemValue = $this->normalizeTipoItem($tipoItem);
        $legacyTipoRegistro = $this->resolveLegacyTipoRegistro($tipoRegistro, $tipoItemValue);

        if ($this->hasTipoItemColumn()) {
            $columns[] = 'tipo_item';
            $params[] = $tipoItemValue;
        }

        $columns[] = 'tipo_registro';
        $params[] = $legacyTipoRegistro;

        $columns[] = 'unidade_medida';
        $params[] = $unidadeMedida;

        $columns[] = 'saldo_atual';
        $params[] = $saldoAtual;

        $columns[] = 'sinonimos';
        $params[] = $synonyms;

        $columns[] = 'requer_comprimento';
        $params[] = $requerComprimento;

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $statement = $this->pdo->prepare('INSERT INTO item (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')');
        $statement->execute($params);

        return (int)$this->pdo->lastInsertId();
    }

    public function adjustStock(int $itemId, int $delta): bool
    {
        $statement = $this->pdo->prepare('UPDATE item SET saldo_atual = saldo_atual + ? WHERE id_item = ?');
        return $statement->execute([$delta, $itemId]);
    }

    public function updateThresholds(int $itemId, int $minQuantity, int $desiredQuantity, int $purchaseBuffer = 0, string $synonyms = '', string $unidadeMedida = 'un', int $requerComprimento = 0, ?string $tipoItem = null): bool
    {
        $statement = $this->pdo->prepare('UPDATE item SET quantidade_minima = ?, quantidade_desejavel = ?, quantidade_sobra_compra = ?, sinonimos = ?, unidade_medida = ?, requer_comprimento = ? WHERE id_item = ?');
        $updated = $statement->execute([$minQuantity, $desiredQuantity, $purchaseBuffer, $synonyms, $unidadeMedida, $requerComprimento, $itemId]);

        if ($updated && $tipoItem !== null) {
            $this->updateTipoItem($itemId, $tipoItem);
        }

        return $updated;
    }

    public function findByNameAndCondition(string $name, string $condition): ?array
    {
        if (!$this->hasCondicaoColumn()) {
            return $this->findByName($name);
        }

        $statement = $this->pdo->prepare('SELECT * FROM item WHERE item = ? AND condicao = ? LIMIT 1');
        $statement->execute([$name, $condition]);
        $item = $statement->fetch(\PDO::FETCH_ASSOC);
        return $item ?: null;
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

    public function getOrCreateByName(string $name, string $condition = 'novo', int $tipoRegistro = 1, string $synonyms = '', string $unidadeMedida = 'un', int $requerComprimento = 0, int $saldoAtual = 0, string $category = '', string $tipoItem = 'temporario'): int
    {
        $existing = $this->findByNameAndCondition($name, $condition);
        if ($existing !== null) {
            if ($this->hasCategoriaColumn()) {
                $existingCategory = trim($existing['categoria'] ?? '');
                if (($existingCategory === '' || $existingCategory === 'Sem categoria') && $category !== '') {
                    $this->updateCategory((int)$existing['id_item'], $category);
                }
            }

            if ($this->hasTipoItemColumn()) {
                $existingTipoItem = trim((string)($existing['tipo_item'] ?? ''));
                if ($existingTipoItem === '') {
                    $this->updateTipoItem((int)$existing['id_item'], $tipoItem);
                } elseif ($this->normalizeTipoItem($existingTipoItem) !== $this->normalizeTipoItem($tipoItem)) {
                    $this->updateTipoItem((int)$existing['id_item'], $tipoItem);
                }
            }
            return (int)$existing['id_item'];
        }

        return $this->save($category, $name, $condition, $tipoRegistro, $unidadeMedida, $saldoAtual, $synonyms, $requerComprimento, $tipoItem);
    }

    public function updateCategory(int $itemId, string $category): bool
    {
        if (!$this->hasCategoriaColumn()) {
            return false;
        }

        $statement = $this->pdo->prepare('UPDATE item SET categoria = ? WHERE id_item = ?');
        return $statement->execute([$category, $itemId]);
    }

    public function updateTipoItem(int $itemId, string $tipoItem): bool
    {
        $normalizedTipoItem = $this->normalizeTipoItem($tipoItem);
        $legacyTipoRegistro = $this->resolveLegacyTipoRegistro(0, $normalizedTipoItem);

        if ($this->hasTipoItemColumn()) {
            $statement = $this->pdo->prepare('UPDATE item SET tipo_item = ?, tipo_registro = ? WHERE id_item = ?');
            return $statement->execute([$normalizedTipoItem, $legacyTipoRegistro, $itemId]);
        }

        $statement = $this->pdo->prepare('UPDATE item SET tipo_registro = ? WHERE id_item = ?');
        return $statement->execute([$legacyTipoRegistro, $itemId]);
    }

    public function getTipoRegistro(int $id): int
    {
        $statement = $this->pdo->prepare('SELECT tipo_registro FROM item WHERE id_item = ? LIMIT 1');
        $statement->execute([$id]);
        $row = $statement->fetch();
        return isset($row['tipo_registro']) ? (int)$row['tipo_registro'] : 1;
    }

    public function getTipoItem(int $id): string
    {
        $statement = $this->pdo->prepare('SELECT tipo_item, tipo_registro FROM item WHERE id_item = ? LIMIT 1');
        $statement->execute([$id]);
        $row = $statement->fetch();
        if (!empty($row['tipo_item'])) {
            return $this->normalizeTipoItem((string)$row['tipo_item']);
        }

        if (!empty($row['tipo_registro'])) {
            return (int)$row['tipo_registro'] === 1 ? 'permanente' : 'temporario';
        }

        return 'temporario';
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
