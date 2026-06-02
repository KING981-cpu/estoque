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

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS localidade (
            id_localidade INT PRIMARY KEY AUTO_INCREMENT,
            local VARCHAR(100) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

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
}
