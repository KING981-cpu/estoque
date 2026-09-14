<?php

class Database
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = $this->connect();
        $this->initSchema();
    }

    private function env(string $name, string $default): string
    {
        $value = getenv($name);
        return $value !== false ? $value : $default;
    }

    private function connect(): \PDO
    {
        $host = $this->env('DB_HOST', 'db');
        $port = $this->env('DB_PORT', '3306');
        $database = $this->env('DB_NAME', 'estoque');
        $user = $this->env('DB_USER', 'estoque');
        $password = $this->env('DB_PASS', 'estoque_pass');

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);
        $lastException = null;

        for ($attempt = 0; $attempt < 10; $attempt++) {
            try {
                $pdo = new \PDO($dsn, $user, $password, $options);
                $pdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $database));
                $pdo->exec(sprintf('USE `%s`', $database));
                return $pdo;
            } catch (\PDOException $exception) {
                $lastException = $exception;
                sleep(2);
            }
        }

        throw new \RuntimeException('Não foi possível conectar ao banco de dados MySQL: ' . $lastException?->getMessage());
    }

    private function initSchema(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS funcionario (
            id_funcionario INT PRIMARY KEY AUTO_INCREMENT,
            nome VARCHAR(100) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS localidade (
    id_localidade INT PRIMARY KEY AUTO_INCREMENT,
    secretaria VARCHAR(255) NOT NULL DEFAULT '',
    divisao VARCHAR(255) NOT NULL DEFAULT '',
    setor VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

        $this->ensureLocalidadeSchema();

        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS item (
    id_item INT PRIMARY KEY AUTO_INCREMENT,
    categoria VARCHAR(100) NOT NULL DEFAULT '',
    item VARCHAR(50) NOT NULL,
    condicao ENUM('novo', 'usado') NOT NULL DEFAULT 'novo',
    tipo_registro TINYINT(1) NOT NULL DEFAULT 1,
    tipo_item VARCHAR(20) NOT NULL DEFAULT 'temporario',
    unidade_medida VARCHAR(10) NOT NULL DEFAULT 'un',
    saldo_atual INT NOT NULL DEFAULT 0,
    quantidade_minima INT NOT NULL DEFAULT 0,
    quantidade_sobra_compra INT NOT NULL DEFAULT 0,
    sinonimos TEXT NOT NULL,
    requer_comprimento TINYINT(1) NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uniq_item_condicao (item, condicao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL
        );

        $this->ensureItemSchema();

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS item_notificacao_email (
            id INT PRIMARY KEY AUTO_INCREMENT,
            id_item INT NOT NULL,
            email VARCHAR(255) NOT NULL,
            FOREIGN KEY (id_item) REFERENCES item(id_item)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS movimentacao (
            id_movimentacao INT PRIMARY KEY AUTO_INCREMENT,
            tipo ENUM(\'entrada\', \'saída\') NOT NULL,
            data_item DATE NOT NULL,
            quantidade INT NOT NULL,
            assinatura LONGTEXT NOT NULL,
            uso ENUM(\'Consumo\', \'Empréstimo\') NOT NULL,
            tipo_registro TINYINT(1) NOT NULL DEFAULT 1,
            `observação` VARCHAR(200) NOT NULL,
            patrimonio VARCHAR(255) NOT NULL DEFAULT \'\',
            condicao_item VARCHAR(50) NOT NULL DEFAULT \'\',
            id_funcionario INT,
            id_localidade INT,
            id_item INT,
            FOREIGN KEY (id_funcionario) REFERENCES funcionario(id_funcionario),
            FOREIGN KEY (id_localidade) REFERENCES localidade(id_localidade),
            FOREIGN KEY (id_item) REFERENCES item(id_item)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $this->ensureMovementSchema();
        $this->synchronizeItemTipoRegistroWithMovements();
    }

    private function ensureItemSchema(): void
    {
        $columns = $this->getTableColumns('item');
        if (empty($columns)) {
            return;
        }

        $needsAlter = !isset($columns['quantidade_minima']) || !isset($columns['quantidade_desejavel']) || !isset($columns['quantidade_sobra_compra']) || !isset($columns['sinonimos']) || !isset($columns['tipo_registro']) || !isset($columns['tipo_item']) || !isset($columns['unidade_medida']) || !isset($columns['saldo_atual']) || !isset($columns['requer_comprimento']) || !isset($columns['categoria']) || !isset($columns['condicao']) || !isset($columns['ativo']);
        if (!$needsAlter) {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            if (!isset($columns['quantidade_minima'])) {
                $this->pdo->exec('ALTER TABLE item ADD COLUMN quantidade_minima INT NOT NULL DEFAULT 0');
            }

            if (!isset($columns['quantidade_desejavel'])) {
                $this->pdo->exec('ALTER TABLE item ADD COLUMN quantidade_desejavel INT NOT NULL DEFAULT 0');
            }

            if (!isset($columns['quantidade_sobra_compra'])) {
                $this->pdo->exec('ALTER TABLE item ADD COLUMN quantidade_sobra_compra INT NOT NULL DEFAULT 0');
            }

            if (!isset($columns['sinonimos'])) {
                $this->pdo->exec('ALTER TABLE item ADD COLUMN sinonimos TEXT NOT NULL');
            }

            if (!isset($columns['tipo_registro'])) {
                $this->pdo->exec('ALTER TABLE item ADD COLUMN tipo_registro TINYINT(1) NOT NULL DEFAULT 1');
            }
            if (!isset($columns['tipo_item'])) {
                $this->pdo->exec("ALTER TABLE item ADD COLUMN tipo_item VARCHAR(20) NOT NULL DEFAULT 'temporario'");
            }
            if (!isset($columns['condicao'])) {
                $this->pdo->exec("ALTER TABLE item ADD COLUMN condicao ENUM('novo','usado') NOT NULL DEFAULT 'novo'");
            }
            if (!isset($columns['categoria'])) {
                $this->pdo->exec('ALTER TABLE item ADD COLUMN categoria VARCHAR(100) NOT NULL DEFAULT ""');
            }

            if (!isset($columns['unidade_medida'])) {
                $this->pdo->exec("ALTER TABLE item ADD COLUMN unidade_medida VARCHAR(10) NOT NULL DEFAULT 'un'");
            }

            if (!isset($columns['saldo_atual'])) {
                $this->pdo->exec('ALTER TABLE item ADD COLUMN saldo_atual INT NOT NULL DEFAULT 0');
            }

            if (!isset($columns['requer_comprimento'])) {
                $this->pdo->exec('ALTER TABLE item ADD COLUMN requer_comprimento TINYINT(1) NOT NULL DEFAULT 0');
            }

            if (!isset($columns['ativo'])) {
                $this->pdo->exec('ALTER TABLE item ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1');
            }

            if ($this->hasItemColumn('categoria')) {
                $this->pdo->exec("UPDATE item SET categoria = 'Sem categoria' WHERE categoria = ''");
            }

            if ($this->hasItemColumn('condicao')) {
                $this->pdo->exec("UPDATE item SET condicao = 'novo' WHERE condicao = '' OR condicao IS NULL");
            }

            if ($this->hasItemColumn('tipo_item')) {
                $this->pdo->exec("UPDATE item SET tipo_item = CASE WHEN COALESCE(tipo_registro, 1) = 1 THEN 'permanente' ELSE 'temporario' END WHERE tipo_item IS NULL OR tipo_item = ''");
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function ensureMovementSchema(): void
    {
        $columns = $this->getTableColumns('movimentacao');
        if (empty($columns)) {
            return;
        }

        if (!isset($columns['tipo_registro']) || !isset($columns['patrimonio']) || !isset($columns['condicao_item']) || isset($columns['comprimento_movimentado'])) {
            $this->pdo->beginTransaction();
            try {
                if (!isset($columns['tipo_registro'])) {
                    $this->pdo->exec('ALTER TABLE movimentacao ADD COLUMN tipo_registro TINYINT(1) NOT NULL DEFAULT 1');
                }
                if (!isset($columns['patrimonio'])) {
                    $this->pdo->exec('ALTER TABLE movimentacao ADD COLUMN patrimonio VARCHAR(255) NOT NULL DEFAULT ""');
                }
                if (!isset($columns['condicao_item'])) {
                    $this->pdo->exec('ALTER TABLE movimentacao ADD COLUMN condicao_item VARCHAR(50) NOT NULL DEFAULT ""');
                }
                if (isset($columns['comprimento_movimentado'])) {
                    $this->pdo->exec('ALTER TABLE movimentacao DROP COLUMN comprimento_movimentado');
                }
                $this->pdo->commit();
            } catch (\Throwable $exception) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $exception;
            }
        }
    }

    public function pdo(): \PDO
    {
        return $this->pdo;
    }

    private function synchronizeItemTipoRegistroWithMovements(): void
    {
        $this->pdo->exec(<<<'SQL'
UPDATE item i
JOIN (
    SELECT first_item.id_item, m.tipo_registro
    FROM (
        SELECT id_item, MIN(id_movimentacao) AS first_mov
        FROM movimentacao
        GROUP BY id_item
    ) AS first_item
    JOIN movimentacao m ON m.id_item = first_item.id_item AND m.id_movimentacao = first_item.first_mov
) AS first_mov ON first_mov.id_item = i.id_item
SET i.tipo_registro = first_mov.tipo_registro
WHERE i.tipo_registro <> first_mov.tipo_registro;
SQL
        );
    }

    private function ensureLocalidadeSchema(): void
    {
        $columns = $this->getTableColumns('localidade');
        if (empty($columns)) {
            return;
        }

        $hasVarcharLocalidade = isset($columns['secretaria'], $columns['divisao'], $columns['setor'])
            && $this->isVarcharColumn($columns['secretaria'])
            && $this->isVarcharColumn($columns['divisao'])
            && $this->isVarcharColumn($columns['setor']);

        if ($hasVarcharLocalidade && !isset($columns['local'])) {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            if (!isset($columns['secretaria'])) {
                $this->pdo->exec('ALTER TABLE localidade ADD COLUMN secretaria VARCHAR(255) NOT NULL DEFAULT ""');
            }
            if (!isset($columns['divisao'])) {
                $this->pdo->exec('ALTER TABLE localidade ADD COLUMN divisao VARCHAR(255) NOT NULL DEFAULT ""');
            }
            if (!isset($columns['setor'])) {
                $this->pdo->exec('ALTER TABLE localidade ADD COLUMN setor VARCHAR(255) NOT NULL DEFAULT ""');
            }

            if (isset($columns['local'])) {
                $statement = $this->pdo->query('SELECT id_localidade, local FROM localidade');
                foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    [$secretaria, $divisao, $setor] = $this->parseLocalidadePath($row['local']);
                    $update = $this->pdo->prepare('UPDATE localidade SET secretaria = ?, divisao = ?, setor = ? WHERE id_localidade = ?');
                    $update->execute([$secretaria, $divisao, $setor, $row['id_localidade']]);
                }
            }

            $this->executeWithoutWarningException(<<<'SQL'
ALTER TABLE localidade
    MODIFY COLUMN secretaria VARCHAR(255) NOT NULL DEFAULT '',
    MODIFY COLUMN divisao VARCHAR(255) NOT NULL DEFAULT '',
    MODIFY COLUMN setor VARCHAR(255) NOT NULL DEFAULT ''
SQL);

            if (isset($columns['local'])) {
                $this->pdo->exec('ALTER TABLE localidade DROP COLUMN local');
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function getTableColumns(string $table): array
    {
        $statement = $this->pdo->prepare('SHOW COLUMNS FROM ' . $table);
        $statement->execute();
        $columns = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $column) {
            $columns[$column['Field']] = $column['Type'];
        }

        return $columns;
    }

    private function isVarcharColumn(string $columnType): bool
    {
        return str_starts_with(strtolower($columnType), 'varchar(');
    }

    private function parseLocalidadePath(string $localidadePath): array
    {
        $parts = array_map('trim', explode('>', $localidadePath));
        $parts = array_map(fn ($part) => $part === '' ? null : $part, $parts);
        $parts = array_values(array_filter($parts, fn ($part) => $part !== null));

        while (count($parts) < 3) {
            $parts[] = '';
        }

        if ($parts[2] === '') {
            $parts[2] = 'Sem setor disponível';
        }

        return [$parts[0], $parts[1], $parts[2]];
    }

    private function executeWithoutWarningException(string $sql): void
    {
        $previousMode = $this->pdo->getAttribute(\PDO::ATTR_ERRMODE);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING);
        try {
            $this->pdo->exec($sql);
        } finally {
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, $previousMode);
        }
    }
}
