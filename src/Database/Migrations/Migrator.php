<?php

namespace Axcel\Database\Migrations;

use Axcel\Database\DatabaseManager;
use Axcel\Database\Schema\Schema;
use Axcel\Foundation\Application;
use PDO;
use Exception;
use DirectoryIterator;
use PDOException;

class Migrator
{
    protected DatabaseManager $databaseManager;
    protected Application $app;
    protected string $migrationPath;
    protected string $table = 'migrations';

    public function __construct(DatabaseManager $databaseManager, Application $app)
    {
        $this->databaseManager = $databaseManager;
        $this->app = $app;
        $this->migrationPath = $app->basePath('database/migrations');

        Schema::setDatabaseManager($databaseManager);
        Schema::setConnection($databaseManager->getDefaultConnection());
    }

    /**
     * Run all pending migrations
     */
    public function run(): array
    {
        $this->createMigrationsTable();

        $migrations = $this->getPendingMigrations();
        $ran = [];

        foreach ($migrations as $migration) {
            $this->runMigration($migration);
            $ran[] = $migration;
            echo "Migrating: {$migration}\n";
        }

        return $ran;
    }

    /**
     * Rollback the last batch of migrations
     */
    public function rollback(int $steps = 1): array
    {
        $this->createMigrationsTable();

        $migrations = $this->getMigrationsToRollback($steps);
        $rolledBack = [];

        foreach ($migrations as $migration) {
            $this->rollbackMigration($migration);
            $rolledBack[] = $migration;
        }



        return $rolledBack;
    }

    /**
     * Reset all migrations
     */
    public function reset(): array
    {
        $this->createMigrationsTable();

        $migrations = $this->getRanMigrations();
        $reset = [];

        // Rollback in reverse order
        foreach (array_reverse($migrations) as $migration) {
            $this->rollbackMigration($migration);
            $reset[] = $migration;
            echo "Reset: {$migration}\n";
        }

        return $reset;
    }

    /**
     * Get the status of all migrations
     */
    public function status(): array
    {
        $this->createMigrationsTable();

        $allMigrations = $this->getAllMigrationFiles();
        $ranMigrations = $this->getRanMigrations();
        $status = [];

        foreach ($allMigrations as $migration) {
            $status[] = [
                'migration' => $migration,
                'status' => in_array($migration, $ranMigrations) ? 'Ran' : 'Pending'
            ];
        }

        return $status;
    }

    /**
     * Create the migrations table if it doesn't exist
     */
    protected function createMigrationsTable(): void
    {
        if (!Schema::hasTable($this->table)) {
            Schema::create($this->table, function ($table) {
                $table->id();
                $table->string('migration');
                $table->integer('batch');
            });
        }
    }

    /**
     * Get all pending migrations
     */
    protected function getPendingMigrations(): array
    {
        $allMigrations = $this->getAllMigrationFiles();
        $ranMigrations = $this->getRanMigrations();

        // Filter out migrations that have already been recorded
        $pendingMigrations = array_diff($allMigrations, $ranMigrations);

        // If there are no pending migrations, return empty array
        if (empty($pendingMigrations)) {
            return [];
        }

        return $pendingMigrations;
    }
    /**
     * Get all migration files
     */
    protected function getAllMigrationFiles(): array
    {
        if (!is_dir($this->migrationPath)) {
            return [];
        }

        $migrations = [];
        $iterator = new DirectoryIterator($this->migrationPath);

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $migrations[] = $file->getBasename('.php');
            }
        }

        sort($migrations);
        return $migrations;
    }

    /**
     * Get all ran migrations from database
     */
    protected function getRanMigrations(): array
    {
        $connection = $this->databaseManager->connection();
        $statement = $connection->prepare("SELECT migration FROM {$this->table} ORDER BY batch, id");
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get migrations to rollback
     */
    protected function getMigrationsToRollback(int $steps): array
    {
        $connection = $this->databaseManager->connection();

        // Step 1: Get the last N batch numbers manually
        $batchStmt = $connection->query("
        SELECT DISTINCT batch FROM {$this->table} 
        ORDER BY batch DESC
    ");
        $batches = $batchStmt->fetchAll(PDO::FETCH_COLUMN);

        // Only take the number of steps specified
        $targetBatches = array_slice($batches, 0, $steps);

        if (empty($targetBatches)) {
            return [];
        }

        // Step 2: Build a dynamic placeholder string (?, ?, ?) for IN clause
        $placeholders = implode(',', array_fill(0, count($targetBatches), '?'));

        $sql = "
        SELECT migration FROM {$this->table} 
        WHERE batch IN ({$placeholders})
        ORDER BY batch DESC, id DESC
    ";

        $stmt = $connection->prepare($sql);
        $stmt->execute($targetBatches);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }


    /**
     * Run a specific migration
     */
    protected function runMigration(string $migration): void
    {
        try {
            $migrationInstance = $this->loadMigration($migration);
            $migrationInstance->up();
            $this->recordMigration($migration);
        } catch (PDOException $e) {
            // If the error is about table already existing, just record the migration
            if ($e->getCode() == '42S01') {
                $this->recordMigration($migration);
                echo "Table already exists for migration: {$migration}, recording as completed.\n";
                return;
            }
            throw $e;
        }
    }

    /**
     * Rollback a specific migration
     */
    protected function rollbackMigration(string $migration): void
    {
        $migrationInstance = $this->loadMigration($migration);

        try {
            $migrationInstance->down();
            $this->removeMigrationRecord($migration);
        } catch (Exception $e) {
            throw new Exception("Rollback of {$migration} failed: " . $e->getMessage());
        }
    }

    /**
     * Load a migration instance
     */
    protected function loadMigration(string $migration): Migration
    {
        $file = $this->migrationPath . '/' . $migration . '.php';

        if (!file_exists($file)) {
            throw new Exception("Migration file not found: {$file}");
        }

        // Load the migration file
        $migrationClass = require $file;

        // Check if it's an anonymous class (instance of Migration)
        if (!$migrationClass instanceof Migration) {
            throw new Exception("Migration {$migration} must return an anonymous class that extends Migration");
        }

        return $migrationClass;
    }

    /**
     * Record a migration in the database
     */
    protected function recordMigration(string $migration): void
    {
        $batch = $this->getNextBatchNumber();
        $connection = $this->databaseManager->connection();

        $statement = $connection->prepare("
            INSERT INTO {$this->table} (migration, batch) 
            VALUES (?, ?)
        ");
        $statement->execute([$migration, $batch]);
    }

    /**
     * Remove a migration record from the database
     */
    protected function removeMigrationRecord(string $migration): void
    {
        $connection = $this->databaseManager->connection();
        $statement = $connection->prepare("DELETE FROM {$this->table} WHERE migration = ?");
        $statement->execute([$migration]);
    }

    /**
     * Get the next batch number
     */
    protected function getNextBatchNumber(): int
    {
        $connection = $this->databaseManager->connection();
        $statement = $connection->query("SELECT MAX(batch) as max_batch FROM {$this->table}");
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return ((int) $result['max_batch']) + 1;
    }

    /**
     * Set the migration path
     */
    public function setMigrationPath(string $path): void
    {
        $this->migrationPath = $path;
    }

    /**
     * Get the migration path
     */
    public function getMigrationPath(): string
    {
        return $this->migrationPath;
    }
}
