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

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS item (
            id_item INT PRIMARY KEY AUTO_INCREMENT,
            item VARCHAR(50) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS movimentacao (
            id_movimentacao INT PRIMARY KEY AUTO_INCREMENT,
            tipo ENUM(\'entrada\', \'saída\') NOT NULL,
            data_item DATE NOT NULL,
            quantidade INT NOT NULL,
            assinatura LONGTEXT NOT NULL,
            uso ENUM(\'Consumo\', \'Empréstimo\') NOT NULL,
            `observação` VARCHAR(200) NOT NULL,
            id_funcionario INT,
            id_localidade INT,
            id_item INT,
            FOREIGN KEY (id_funcionario) REFERENCES funcionario(id_funcionario),
            FOREIGN KEY (id_localidade) REFERENCES localidade(id_localidade),
            FOREIGN KEY (id_item) REFERENCES item(id_item)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    public function pdo(): \PDO
    {
        return $this->pdo;
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
