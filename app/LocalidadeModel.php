<?php

require_once __DIR__ . '/Database.php';

class LocalidadeModel
{
    private const NO_SETOR = 'Sem setor disponível';
    private \PDO $pdo;

    public function __construct(Database $database)
    {
        $this->pdo = $database->pdo();
    }

    public function all(): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM localidade ORDER BY secretaria ASC, divisao ASC, setor ASC');
        $statement->execute();
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM localidade WHERE id_localidade = ?');
        $statement->execute([$id]);
        $localidade = $statement->fetch(\PDO::FETCH_ASSOC);
        return $localidade ?: null;
    }

    public function save(string $local): int
    {
        [$secretaria, $divisao, $setor] = $this->parseLocalidadePath($local);
        return $this->saveFields($secretaria, $divisao, $setor);
    }

    public function saveFields(string $secretaria, string $divisao, string $setor): int
    {
        $statement = $this->pdo->prepare('INSERT INTO localidade (secretaria, divisao, setor) VALUES (?, ?, ?)');
        $statement->execute([$secretaria, $divisao, $setor]);
        return (int)$this->pdo->lastInsertId();
    }

    public function findByName(string $local): ?array
    {
        [$secretaria, $divisao, $setor] = $this->parseLocalidadePath($local);
        return $this->findBySecretariaDivisaoSetor($secretaria, $divisao, $setor);
    }

    public function findBySecretariaDivisaoSetor(string $secretaria, string $divisao, string $setor): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM localidade WHERE secretaria = ? AND divisao = ? AND setor = ? LIMIT 1');
        $statement->execute([$secretaria, $divisao, $setor]);
        $localidade = $statement->fetch(\PDO::FETCH_ASSOC);
        return $localidade ?: null;
    }

    public function ensureExists(string $local): int
    {
        $existing = $this->findByName($local);
        if ($existing !== null) {
            return (int)$existing['id_localidade'];
        }

        return $this->save($local);
    }

    private function parseLocalidadePath(string $localidadePath): array
    {
        $parts = array_filter(array_map('trim', explode('>', $localidadePath)), fn ($part) => $part !== '');
        $parts = array_values($parts);

        while (count($parts) < 3) {
            $parts[] = self::NO_SETOR;
        }

        return [$parts[0], $parts[1], $parts[2]];
    }
}
