<?php

namespace Axcel\Database;

use PDO;
use PDOStatement;
use PDOException;

class Connection
{
    protected PDO $pdo;
    protected array $config;
    protected int $transactionLevel = 0;

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = $config;
    }

    /**
     * Get the PDO connection.
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Execute a select statement and return the results.
     */
    public function select(string $query, array $bindings = []): array
    {
        return $this->run($query, $bindings, function ($statement) {
            return $statement->fetchAll();
        });
    }

    /**
     * Execute a select statement and return the first result.
     */
    public function selectOne(string $query, array $bindings = []): ?array
    {
        $results = $this->select($query, $bindings);
        return $results[0] ?? null;
    }

    /**
     * Execute an insert statement and return the insert ID.
     */
    public function insert(string $query, array $bindings = []): string|false
    {
        $this->statement($query, $bindings);
        return $this->pdo->lastInsertId();
    }

    /**
     * Execute an update statement and return the number of affected rows.
     */
    public function update(string $query, array $bindings = []): int
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Execute a delete statement and return the number of affected rows.
     */
    public function delete(string $query, array $bindings = []): int
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Execute a general statement.
     */
    public function statement(string $query, array $bindings = []): bool
    {
        return $this->run($query, $bindings, function ($statement) {
            return $statement->rowCount() > 0;
        });
    }

    /**
     * Execute an affecting statement and return the number of affected rows.
     */
    public function affectingStatement(string $query, array $bindings = []): int
    {
        return $this->run($query, $bindings, function ($statement) {
            return $statement->rowCount();
        });
    }

    /**
     * Execute a callback within a database transaction.
     */
    public function transaction(callable $callback)
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }

        return $result;
    }

    /**
     * Start a new database transaction.
     */
    public function beginTransaction(): void
    {
        $this->transactionLevel++;

        if ($this->transactionLevel === 1) {
            $this->pdo->beginTransaction();
        }
    }

    /**
     * Commit the active database transaction.
     */
    public function commit(): void
    {
        if ($this->transactionLevel === 1) {
            $this->pdo->commit();
        }

        $this->transactionLevel = max(0, $this->transactionLevel - 1);
    }

    /**
     * Rollback the active database transaction.
     */
    public function rollback(): void
    {
        if ($this->transactionLevel === 1) {
            $this->pdo->rollback();
        }

        $this->transactionLevel = max(0, $this->transactionLevel - 1);
    }

    /**
     * Get the current transaction level.
     */
    public function transactionLevel(): int
    {
        return $this->transactionLevel;
    }

    /**
     * Execute a prepared statement with bindings.
     */
    protected function run(string $query, array $bindings, callable $callback)
    {
        try {
            $statement = $this->prepared($query);

            $this->bindValues($statement, $bindings);

            $statement->execute();

            return $callback($statement);
        } catch (PDOException $e) {
            throw new DatabaseException(
                "Database query failed: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Prepare the given SQL statement.
     */
    protected function prepared(string $query): PDOStatement
    {
        return $this->pdo->prepare($query);
    }

    /**
     * Bind values to their parameters in the given statement.
     */
    protected function bindValues(PDOStatement $statement, array $bindings): void
    {
        foreach ($bindings as $key => $value) {
            $statement->bindValue(
                is_string($key) ? $key : $key + 1,
                $value,
                $this->getPdoType($value)
            );
        }
    }

    /**
     * Get the PDO type for the given value.
     */
    protected function getPdoType($value): int
    {
        if (is_int($value)) {
            return PDO::PARAM_INT;
        }

        if (is_bool($value)) {
            return PDO::PARAM_BOOL;
        }

        if (is_null($value)) {
            return PDO::PARAM_NULL;
        }

        return PDO::PARAM_STR;
    }

    /**
     * Get the connection configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get the connection name.
     */
    public function getName(): ?string
    {
        return $this->config['name'] ?? null;
    }

    /**
     * Get the database name.
     */
    public function getDatabaseName(): ?string
    {
        return $this->config['database'] ?? null;
    }

    /**
     * Get the table prefix.
     */
    public function getTablePrefix(): string
    {
        return $this->config['prefix'] ?? '';
    }

    /**
     * Set the table prefix.
     */
    public function setTablePrefix(string $prefix): void
    {
        $this->config['prefix'] = $prefix;
    }

    /**
     * Determine if the connection is in a transaction.
     */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Get the database connection info.
     */
    public function getConnectionInfo(): array
    {
        return [
            'driver' => $this->config['driver'] ?? 'unknown',
            'host' => $this->config['host'] ?? null,
            'port' => $this->config['port'] ?? null,
            'database' => $this->config['database'] ?? null,
            'prefix' => $this->getTablePrefix(),
        ];
    }

    /**
     * Execute a raw SQL query.
     */
    public function raw(string $query): mixed
    {
        return $this->pdo->query($query);
    }

    /**
     * Quote a string for use in a query.
     */
    public function quote(string $value): string
    {
        return $this->pdo->quote($value);
    }

    /**
     * Get the last insert ID.
     */
    public function lastInsertId(?string $name = null): string|false
    {
        return $this->pdo->lastInsertId($name);
    }
}

/**
 * Custom database exception class.
 */
class DatabaseException extends \Exception
{
    //
}
