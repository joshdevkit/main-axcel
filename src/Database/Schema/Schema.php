<?php

namespace Axcel\Database\Schema;

use Axcel\Database\Schema\Blueprint;
use Axcel\Database\Schema\Grammars\MySqlGrammar;
use Axcel\Database\DatabaseManager;
use PDO;
use Closure;

class Schema
{
    protected static ?DatabaseManager $databaseManager = null;
    protected static string $connection = 'default';
    protected static ?MySqlGrammar $grammar = null;

    /**
     * Set the database manager
     */
    public static function setDatabaseManager(DatabaseManager $manager): void
    {
        static::$databaseManager = $manager;
        static::$grammar = new MySqlGrammar();
    }

    /**
     * Set the connection name
     */
    public static function setConnection(string $connection): void
    {
        static::$connection = $connection;
    }

    /**
     * Get the database connection
     */
    protected static function getConnection(): PDO
    {
        if (!static::$databaseManager) {
            throw new \RuntimeException('Database manager not set. Call Schema::setDatabaseManager() first.');
        }

        return static::$databaseManager->connection(static::$connection);
    }

    /**
     * Create a new table
     */
    public static function create(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        $sql = static::$grammar->compileCreate($blueprint);
        static::execute($sql);
    }

    /**
     * Drop a table
     */
    public static function drop(string $table): void
    {
        $blueprint = new Blueprint($table);
        $sql = static::$grammar->compileDrop($blueprint);
        static::execute($sql);
    }

    /**
     * Drop a table if it exists
     */
    public static function dropIfExists(string $table): void
    {
        if (static::hasTable($table)) {
            static::drop($table);
        }
    }

    /**
     * Modify an existing table
     */
    public static function table(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        $statements = static::$grammar->compileAlter($blueprint);
        foreach ($statements as $statement) {
            static::execute($statement);
        }
    }

    /**
     * Check if a table exists
     */
    public static function hasTable(string $table): bool
    {
        $connection = static::getConnection();
        $sql = "SHOW TABLES LIKE ?";
        $statement = $connection->prepare($sql);
        $statement->execute([$table]);

        return $statement->rowCount() > 0;
    }

    /**
     * Check if a column exists in a table
     */
    public static function hasColumn(string $table, string $column): bool
    {
        $connection = static::getConnection();
        $sql = "SHOW COLUMNS FROM `{$table}` LIKE ?";
        $statement = $connection->prepare($sql);
        $statement->execute([$column]);

        return $statement->rowCount() > 0;
    }

    /**
     * Get all tables in the database
     */
    public static function getTables(): array
    {
        $connection = static::getConnection();
        $statement = $connection->query('SHOW TABLES');

        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get all columns for a table
     */
    public static function getColumns(string $table): array
    {
        $connection = static::getConnection();
        $statement = $connection->query("SHOW COLUMNS FROM `{$table}`");

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Execute a SQL statement
     */
    protected static function execute(string $sql): void
    {
        $connection = static::getConnection();
        $connection->exec($sql);
    }

    /**
     * Rename a table
     */
    public static function rename(string $from, string $to): void
    {
        $sql = "RENAME TABLE `{$from}` TO `{$to}`";
        static::execute($sql);
    }

    /**
     * Enable foreign key checks
     */
    public static function enableForeignKeyConstraints(): void
    {
        static::execute('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Disable foreign key checks
     */
    public static function disableForeignKeyConstraints(): void
    {
        static::execute('SET FOREIGN_KEY_CHECKS=0');
    }
}
