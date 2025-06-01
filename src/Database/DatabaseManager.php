<?php

namespace Axcel\Database;

use PDO;
use PDOException;

class DatabaseManager
{
    /**
     * The active connection instances.
     *
     * @var array
     */
    protected $connections = [];

    /**
     * The database connection configuration.
     *
     * @var array
     */
    protected $config;

    /**
     * The default connection name.
     *
     * @var string
     */
    protected $default;

    /**
     * Create a new database manager instance.
     *
     * @param  array  $config
     * @return void
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->default = $config['default'];
    }

    /**
     * Get a database connection instance.
     *
     * @param  string|null  $name
     * @return \PDO
     */
    public function connection($name = null)
    {
        $name = $name ?: $this->default;

        // If we already have an active connection for this name, return it
        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        $connection = $this->makeConnection($name);

        $this->connections[$name] = $connection;

        return $connection;
    }

    /**
     * Make a new database connection.
     *
     * @param  string  $name
     * @return \PDO
     * 
     * @throws \Exception
     */
    protected function makeConnection($name)
    {
        $config = $this->configuration($name);

        return $this->createConnection($config);
    }

    /**
     * Get the configuration for a connection.
     *
     * @param  string  $name
     * @return array
     * 
     * @throws \Exception
     */
    protected function configuration($name)
    {
        $connections = $this->config['connections'];

        // If requesting 'default' but it doesn't exist, use the actual default connection
        if ($name === 'default' && !isset($connections[$name])) {
            $name = $this->default;
        }

        if (!isset($connections[$name])) {
            throw new \Exception("Database connection [{$name}] not configured.");
        }

        return $connections[$name];
    }

    /**
     * Create a new PDO connection.
     *
     * @param  array  $config
     * @return \PDO
     * 
     * @throws \Exception
     */
    protected function createConnection(array $config)
    {
        $dsn = $this->getDsn($config);

        try {
            if ($config['driver'] === 'sqlite') {
                $connection = new PDO(
                    $dsn,
                    null,
                    null,
                    $config['options'] ?? []
                );
            } else {
                $connection = new PDO(
                    $dsn,
                    $config['username'],
                    $config['password'],
                    $config['options'] ?? []
                );
            }

            return $connection;
        } catch (PDOException $e) {
            throw new \Exception("Could not connect to database: " . $e->getMessage());
        }
    }


    /**
     * Get the DSN string for a connection.
     *
     * @param  array  $config
     * @return string
     */
    protected function getDsn(array $config)
    {
        if (isset($config['url']) && !empty($config['url'])) {
            return $config['url'];
        }

        $driver = $config['driver'];

        if ($driver === 'sqlite') {
            return $driver . ':' . $config['database'];
        }

        if ($driver === 'pgsql') {
            // PostgreSQL DSN without charset param
            return $driver . ':host=' . $config['host']
                . ';port=' . $config['port']
                . ';dbname=' . $config['database'];
        }

        // default for mysql, etc.
        return $driver . ':host=' . $config['host']
            . ';port=' . $config['port']
            . ';dbname=' . $config['database']
            . ';charset=' . $config['charset'];
    }

    /**
     * Get the underlying PDO instance.
     *
     * @param string|null $name
     * @return \PDO
     */
    public function getPdo($name = null): \PDO
    {
        return $this->connection($name);
    }


    /**
     * Get the default connection name.
     *
     * @return string
     */
    public function getDefaultConnection()
    {
        return $this->default;
    }

    /**
     * Set the default connection name.
     *
     * @param  string  $name
     * @return void
     */
    public function setDefaultConnection($name)
    {
        $this->default = $name;
    }

    /**
     * Get the database configuration.
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }
}
