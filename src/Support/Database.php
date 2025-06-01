<?php

namespace Axcel\Support;

use Axcel\Database\Builder;
use Axcel\Database\DatabaseManager;
use Axcel\Database\Model;
use Axcel\Database\QueryBuilder;
use Axcel\Foundation\Application;

class Database
{
    /**
     * The database connection.
     *
     * @var \PDO
     */
    protected static $connection;

    /**
     * The active database connection name.
     *
     * @var string
     */
    protected static $connectionName = null;

    /**
     * The model to cast results to, if any.
     *
     * @var string|null
     */
    protected static $model = null;

    /**
     * Set the database connection.
     *
     * @param  \PDO  $connection
     * @return void
     */
    public static function setConnection(\PDO $connection)
    {
        static::$connection = $connection;
    }

    /**
     * Get the database connection.
     *
     * @return \PDO
     */
    public static function getConnection()
    {
        if (!static::$connection) {
            static::resolveConnection();
        }

        return static::$connection;
    }

    /**
     * Resolve the database connection instance.
     *
     * @return void
     */
    protected static function resolveConnection()
    {
        $app = Application::getInstance();

        if ($app) {
            $dbManager = $app->get('db');

            if ($dbManager instanceof DatabaseManager) {
                // If no specific connection name is set, use null to get default
                static::$connection = $dbManager->connection(static::$connectionName);
                return;
            }
        }

        throw new \RuntimeException('No database connection has been set.');
    }

    /**
     * Set the connection name to use.
     *
     * @param  string|null  $name
     * @return static
     */
    public static function connection($name = null)
    {
        static::$connectionName = $name;
        static::$connection = null;

        return new static;
    }

    /**
     * Set the model to use for query results.
     *
     * @param  string|Model  $model
     * @return static
     */
    public static function asModel($model)
    {
        if (is_object($model)) {
            static::$model = get_class($model);
        } else {
            static::$model = $model;
        }

        return new static;
    }

    /**
     * Begin a fluent query against a database table.
     *
     * @param  string  $table
     * @return \Axcel\Database\QueryBuilder
     */
    public static function table($table)
    {
        $queryBuilder = new QueryBuilder(static::getConnection());
        $builder = new Builder($queryBuilder);

        // Attempt to auto-bind a model class based on table name
        $modelClass = static::guessModelFromTable($table);

        if (class_exists($modelClass)) {
            $builder->setModel(new $modelClass);
        }

        $queryBuilder->table($table); // just to make sure

        return $builder;
    }

    protected static function guessModelFromTable($table)
    {
        $model = str_replace(' ', '', ucwords(str_replace('_', ' ', str_singular($table))));
        return "App\\Models\\$model";
    }

    /**
     * Run a select statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return array|Model|Collection
     */
    public static function select($query, $bindings = [])
    {
        $statement = static::getConnection()->prepare($query);
        $statement->execute($bindings);

        $results = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return static::processResults($results);
    }

    /**
     * Process the results based on if a model was specified.
     *
     * @param  array  $results
     * @return array|Model|Collection
     */
    protected static function processResults($results)
    {
        if (static::$model && class_exists(static::$model)) {
            $modelInstance = new (static::$model);
            $collection = $modelInstance->hydrate($results);

            // Reset model for subsequent calls
            static::$model = null;

            return $collection;
        }

        return $results;
    }

    /**
     * Get a single record from the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return mixed|Model|null
     */
    public static function selectOne($query, $bindings = [])
    {
        $statement = static::getConnection()->prepare($query);
        $statement->execute($bindings);

        $result = $statement->fetch(\PDO::FETCH_ASSOC);

        if (!$result) {
            return null;
        }

        if (static::$model && class_exists(static::$model)) {
            $modelInstance = new (static::$model);
            $modelResult = $modelInstance->newFromBuilder($result);

            // Reset model for subsequent calls
            static::$model = null;

            return $modelResult;
        }

        return $result;
    }

    /**
     * Run an insert statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return bool
     */
    public static function insert($query, $bindings = [])
    {
        return static::statement($query, $bindings);
    }

    /**
     * Run an update statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return int
     */
    public static function update($query, $bindings = [])
    {
        return static::affectingStatement($query, $bindings);
    }

    /**
     * Run a delete statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return int
     */
    public static function delete($query, $bindings = [])
    {
        return static::affectingStatement($query, $bindings);
    }

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return bool
     */
    public static function statement($query, $bindings = [])
    {
        $statement = static::getConnection()->prepare($query);

        return $statement->execute($bindings);
    }

    /**
     * Run an SQL statement and get the number of rows affected.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return int
     */
    public static function affectingStatement($query, $bindings = [])
    {
        $statement = static::getConnection()->prepare($query);
        $statement->execute($bindings);

        return $statement->rowCount();
    }

    /**
     * Run a raw, unprepared query against the PDO connection.
     *
     * @param  string  $query
     * @return bool
     */
    public static function unprepared($query)
    {
        return (bool) static::getConnection()->exec($query);
    }

    /**
     * Begin a transaction.
     *
     * @return bool
     */
    public static function beginTransaction()
    {
        return static::getConnection()->beginTransaction();
    }

    /**
     * Commit a transaction.
     *
     * @return bool
     */
    public static function commit()
    {
        return static::getConnection()->commit();
    }

    /**
     * Rollback a transaction.
     *
     * @return bool
     */
    public static function rollBack()
    {
        return static::getConnection()->rollBack();
    }

    /**
     * Get the last insert ID.
     *
     * @param  string|null  $name
     * @return string
     */
    public static function lastInsertId($name = null)
    {
        return static::getConnection()->lastInsertId($name);
    }

    /**
     * Execute a callback within a transaction.
     *
     * @param  callable  $callback
     * @return mixed
     *
     * @throws \Throwable
     */
    public static function transaction(callable $callback)
    {
        static::beginTransaction();

        try {
            $result = $callback();

            static::commit();

            return $result;
        } catch (\Throwable $e) {
            static::rollBack();

            throw $e;
        }
    }

    /**
     * Check if a table exists
     *
     * @param  string  $table
     * @return bool
     */
    public static function hasTable($table)
    {
        try {
            $pdo = static::getConnection();

            // Get the database configuration to determine driver
            $app = Application::getInstance();
            $dbConfig = $app->get('config')->get('database', []);
            $defaultConnection = $dbConfig['default'] ?? 'mysql';
            $connectionConfig = $dbConfig['connections'][$defaultConnection] ?? [];
            $driver = $connectionConfig['driver'] ?? 'mysql';

            switch ($driver) {
                case 'mysql':
                    $query = "SHOW TABLES LIKE ?";
                    break;
                case 'sqlite':
                    $query = "SELECT name FROM sqlite_master WHERE type='table' AND name=?";
                    break;
                case 'pgsql':
                    $query = "SELECT tablename FROM pg_tables WHERE tablename = ?";
                    break;
                default:
                    throw new \InvalidArgumentException("Unsupported driver: {$driver}");
            }

            $statement = $pdo->prepare($query);
            $statement->execute([$table]);

            return $statement->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}
