<?php

require_once __DIR__ . '/Database.php';

class MovementModel
{
    private \PDO $pdo;

    public function __construct(Database $database)
    {
        $this->pdo = $database->pdo();
    }

    public function record(int $itemId, string $tipo, string $dataItem, int $quantidade, string $assinatura, string $uso, int $tipoRegistro, string $observacao, string $patrimonio, ?int $idFuncionario, ?int $idLocalidade): int
    {
        $statement = $this->pdo->prepare('INSERT INTO movimentacao (tipo, data_item, quantidade, assinatura, uso, tipo_registro, `observação`, patrimonio, id_funcionario, id_localidade, id_item) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $statement->execute([
            $tipo,
            $dataItem,
            $quantidade,
            $assinatura,
            $uso,
            $tipoRegistro,
            $observacao,
            $patrimonio,
            $idFuncionario,
            $idLocalidade,
            $itemId,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function history(int $itemId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = 'SELECT m.id_movimentacao, m.tipo, m.data_item, m.quantidade, m.assinatura, m.uso, m.tipo_registro, m.`observação` AS observacao, m.patrimonio AS patrimonio, f.nome AS funcionario, CONCAT_WS(" > ", l.secretaria, l.divisao, l.setor) AS localidade, i.item AS item_name FROM movimentacao m LEFT JOIN funcionario f ON m.id_funcionario = f.id_funcionario LEFT JOIN localidade l ON m.id_localidade = l.id_localidade LEFT JOIN item i ON m.id_item = i.id_item WHERE 1=1';

        $params = [];
        if ($itemId !== null) {
            $query .= ' AND m.id_item = ?';
            $params[] = $itemId;
        }

        if ($startDate !== null) {
            $query .= ' AND m.data_item >= ?';
            $params[] = $startDate;
        }

        if ($endDate !== null) {
            $query .= ' AND m.data_item <= ?';
            $params[] = $endDate;
        }

        $query .= ' ORDER BY m.data_item DESC, m.id_movimentacao DESC';
        $statement = $this->pdo->prepare($query);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function movementReport(int $itemId = null, ?int $tipoRegistro = 1, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = 'SELECT m.id_movimentacao, m.tipo, m.data_item, m.quantidade, m.assinatura, m.uso, m.tipo_registro, m.`observação` AS observacao, m.patrimonio AS patrimonio, f.nome AS funcionario, CONCAT_WS(" > ", l.secretaria, l.divisao, l.setor) AS localidade, i.item AS item_name FROM movimentacao m LEFT JOIN funcionario f ON m.id_funcionario = f.id_funcionario LEFT JOIN localidade l ON m.id_localidade = l.id_localidade LEFT JOIN item i ON m.id_item = i.id_item WHERE 1=1';

        $params = [];
        if ($tipoRegistro === 0 || $tipoRegistro === 1) {
            $query .= ' AND m.tipo_registro = ? AND COALESCE(i.tipo_registro, 1) = ?';
            $params[] = $tipoRegistro;
            $params[] = $tipoRegistro;
        }

        if ($itemId !== null) {
            $query .= ' AND m.id_item = ?';
            $params[] = $itemId;
        }

        if ($startDate !== null) {
            $query .= ' AND m.data_item >= ?';
            $params[] = $startDate;
        }

        if ($endDate !== null) {
            $query .= ' AND m.data_item <= ?';
            $params[] = $endDate;
        }

        $query .= ' ORDER BY m.data_item DESC, m.id_movimentacao DESC';
        $statement = $this->pdo->prepare($query);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function summary(): array
    {
        $query = 'SELECT i.item AS item_name,
                         i.quantidade_minima,
                         i.quantidade_desejavel,
                         SUM(CASE WHEN m.tipo = "entrada" THEN m.quantidade ELSE 0 END) AS quantidade_inicial,
                         SUM(CASE WHEN m.tipo = "saída" THEN m.quantidade ELSE 0 END) AS quantidade_retirada
                  FROM movimentacao m
                  JOIN item i ON m.id_item = i.id_item
                  WHERE m.tipo_registro = 1 AND i.tipo_registro = 1
                  GROUP BY i.id_item, i.item, i.quantidade_minima, i.quantidade_desejavel
                  ORDER BY i.item ASC';

        $statement = $this->pdo->prepare($query);
        $statement->execute();
        $rows = $statement->fetchAll();

        $summary = [];
        foreach ($rows as $row) {
            $itemName = $row['item_name'] ?? '---';
            $initialQuantity = isset($row['quantidade_inicial']) ? (int)$row['quantidade_inicial'] : 0;
            $removedQuantity = isset($row['quantidade_retirada']) ? (int)$row['quantidade_retirada'] : 0;
            $finalQuantity = $initialQuantity - $removedQuantity;

            $summary[] = [
                'item_name' => $itemName,
                'quantidade_minima' => (int)($row['quantidade_minima'] ?? 0),
                'quantidade_desejavel' => (int)($row['quantidade_desejavel'] ?? 0),
                'quantidade_inicial' => $initialQuantity,
                'quantidade_retirada' => $removedQuantity,
                'quantidade_final' => $finalQuantity,
            ];
        }

        return $summary;
    }

    /**
     * Retorna o consumo mensal por item, agrupado por ano e mês.
     */
    public function monthlyConsumptionReport(string $yearMonth = null, ?int $tipoRegistro = null): array
    {
        $query = 'SELECT i.id_item,
                         i.item AS item_name,
                         DATE_FORMAT(m.data_item, "%Y-%m") AS mes,
                         SUM(m.quantidade) AS consumo_mensal
                  FROM movimentacao m
                  JOIN item i ON m.id_item = i.id_item
                  WHERE m.uso = "Consumo"';

        $params = [];
        if ($tipoRegistro === 1) {
            $query .= ' AND COALESCE(i.tipo_registro, 1) = 1 AND m.tipo = "saída" AND COALESCE(m.tipo_registro, 1) = 1';
        } elseif ($tipoRegistro === 0) {
            $query .= ' AND COALESCE(i.tipo_registro, 1) = 0 AND m.tipo = "entrada" AND m.tipo_registro = 0';
        }

        if ($yearMonth !== null) {
            $query .= ' AND DATE_FORMAT(m.data_item, "%Y-%m") = ?';
            $params[] = $yearMonth;
        }

        $query .= ' GROUP BY i.id_item, i.item, mes
                   ORDER BY mes DESC, i.item ASC';

        $statement = $this->pdo->prepare($query);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    /**
     * Retorna a data do primeiro consumo registrado para o item.
     */
    public function getFirstConsumptionDate(int $itemId): ?string
    {
        $statement = $this->pdo->prepare('SELECT MIN(m.data_item) AS primeira_data FROM movimentacao m JOIN item i ON m.id_item = i.id_item WHERE m.id_item = ? AND m.tipo = "saída" AND m.uso = "Consumo" AND m.tipo_registro = 1 AND i.tipo_registro = 1');
        $statement->execute([$itemId]);
        $row = $statement->fetch();
        return !empty($row['primeira_data']) ? $row['primeira_data'] : null;
    }

    /**
     * Retorna o consumo total do item a partir da data fornecida.
     */
    public function totalConsumptionSinceDate(int $itemId, string $startDate): int
    {
        $statement = $this->pdo->prepare('SELECT COALESCE(SUM(m.quantidade), 0) AS total_consumo FROM movimentacao m JOIN item i ON m.id_item = i.id_item WHERE m.id_item = ? AND m.tipo = "saída" AND m.uso = "Consumo" AND m.tipo_registro = 1 AND i.tipo_registro = 1 AND m.data_item >= ?');
        $statement->execute([$itemId, $startDate]);
        $row = $statement->fetch();
        return (int)($row['total_consumo'] ?? 0);
    }
}
