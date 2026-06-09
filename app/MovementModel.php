<?php

require_once __DIR__ . '/Database.php';

class MovementModel
{
    private \PDO $pdo;

    public function __construct(Database $database)
    {
        $this->pdo = $database->pdo();
    }

    public function record(int $itemId, string $tipo, string $dataItem, int $quantidade, string $assinatura, string $uso, string $observacao, ?int $idFuncionario, ?int $idLocalidade): int
    {
        $statement = $this->pdo->prepare('INSERT INTO movimentacao (tipo, data_item, quantidade, assinatura, uso, `observação`, id_funcionario, id_localidade, id_item) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $statement->execute([
            $tipo,
            $dataItem,
            $quantidade,
            $assinatura,
            $uso,
            $observacao,
            $idFuncionario,
            $idLocalidade,
            $itemId,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function history(int $itemId = null): array
    {
        $query = 'SELECT m.id_movimentacao, m.tipo, m.data_item, m.quantidade, m.assinatura, m.uso, m.`observação` AS observacao, f.nome AS funcionario, CONCAT_WS(" > ", l.secretaria, l.divisao, l.setor) AS localidade, i.item AS item_name FROM movimentacao m LEFT JOIN funcionario f ON m.id_funcionario = f.id_funcionario LEFT JOIN localidade l ON m.id_localidade = l.id_localidade LEFT JOIN item i ON m.id_item = i.id_item';

        if ($itemId !== null) {
            $query .= ' WHERE m.id_item = ?';
        }

        $query .= ' ORDER BY m.data_item DESC, m.id_movimentacao DESC';
        $statement = $this->pdo->prepare($query);
        $statement->execute($itemId !== null ? [$itemId] : []);
        return $statement->fetchAll();
    }

    public function summary(): array
    {
        $query = 'SELECT i.item AS item_name,
                         SUM(CASE WHEN m.tipo = "entrada" THEN m.quantidade ELSE 0 END) AS quantidade_inicial,
                         SUM(CASE WHEN m.tipo = "saída" THEN m.quantidade ELSE 0 END) AS quantidade_retirada
                  FROM movimentacao m
                  LEFT JOIN item i ON m.id_item = i.id_item
                  GROUP BY i.item
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
                'quantidade_inicial' => $initialQuantity,
                'quantidade_retirada' => $removedQuantity,
                'quantidade_final' => $finalQuantity,
            ];
        }

        return $summary;
    }
}
