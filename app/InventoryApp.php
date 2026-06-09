<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ItemModel.php';
require_once __DIR__ . '/MovementModel.php';
require_once __DIR__ . '/FuncionarioModel.php';
require_once __DIR__ . '/LocalidadeModel.php';
require_once __DIR__ . '/SpreadsheetLocationLoader.php';
require_once __DIR__ . '/EnvLoader.php';

class InventoryApp
{
    private Database $database;
    private ItemModel $items;
    private MovementModel $movements;
    private FuncionarioModel $funcionarios;
    private LocalidadeModel $localidades;
    private SpreadsheetLocationLoader $spreadsheetLocationLoader;

    public function __construct()
    {
        EnvLoader::load(__DIR__ . '/../.env');
        $this->database = new Database();
        $this->items = new ItemModel($this->database);
        $this->movements = new MovementModel($this->database);
        $this->funcionarios = new FuncionarioModel($this->database);
        $this->localidades = new LocalidadeModel($this->database);
        $this->spreadsheetLocationLoader = new SpreadsheetLocationLoader(__DIR__ . '/../ORGANOGRAMA DAS SECRETARIAS CHEFES E DIRETORES - ATUALIZADO 2026-04-20.xlsx');
    }

    public function getItems(): array
    {
        return $this->items->all();
    }

    public function getFuncionarios(): array
    {
        return $this->funcionarios->all();
    }

    public function getLocalidades(): array
    {
        return $this->localidades->all();
    }

    public function getLocalidadeHierarchy(): array
    {
        return $this->spreadsheetLocationLoader->loadHierarchy();
    }

    public function getMovements(int $itemId = null): array
    {
        return $this->movements->history($itemId);
    }

    public function getMovementSummary(): array
    {
        return $this->movements->summary();
    }

    public function addItem(string $name, int $quantity = 0): int
    {
        $itemId = $this->items->getOrCreateByName($name);

        if ($quantity > 0) {
            $this->movements->record(
                $itemId,
                'entrada',
                date('Y-m-d'),
                $quantity,
                '',
                'Consumo',
                '',
                null,
                null
            );
        }

        return $itemId;
    }

    public function addFuncionario(string $nome): int
    {
        return $this->funcionarios->save($nome);
    }

    public function addLocalidade(string $local): int
    {
        return $this->localidades->save($local);
    }

    public function recordMovimentacao(array $data): int
    {
        $itemId = !empty($data['id_item']) ? (int)$data['id_item'] : null;
        $itemName = trim($data['item_name'] ?? '');

        if ($itemId === null) {
            if ($itemName === '') {
                throw new \InvalidArgumentException('O nome do item é obrigatório.');
            }
            $itemId = $this->items->getOrCreateByName($itemName);
        }

        $localidadeId = null;
        if (!empty($data['id_localidade'])) {
            $localidadeId = (int)$data['id_localidade'];
        } elseif (!empty($data['localidade_path'])) {
            $localidadeId = $this->localidades->ensureExists(trim($data['localidade_path']));
        }

        return $this->movements->record(
            $itemId,
            $data['tipo'],
            $data['data_item'],
            (int)$data['quantidade'],
            $data['assinatura_data'] ?? '',
            $data['uso'],
            $data['observacao'] ?? '',
            !empty($data['id_funcionario']) ? (int)$data['id_funcionario'] : null,
            $localidadeId
        );
    }

    public function getStock(int $itemId): int
    {
        return $this->items->getStock($itemId);
    }
}
