<?php

namespace Axcel\Facades;

use Axcel\Support\Facade;

/**
 * @method static \Axcel\Database\Builder table(string $table)
 * @method static mixed select(string $query, array $bindings = [])
 * @method static mixed selectOne(string $query, array $bindings = [])
 * @method static bool insert(string $query, array $bindings = [])
 * @method static int update(string $query, array $bindings = [])
 * @method static int delete(string $query, array $bindings = [])
 * @method static bool statement(string $query, array $bindings = [])
 * @method static int affectingStatement(string $query, array $bindings = [])
 * @method static bool unprepared(string $query)
 * @method static bool beginTransaction()
 * @method static bool commit()
 * @method static bool rollBack()
 * @method static string lastInsertId(string $name = null)
 * @method static mixed transaction(callable $callback)
 * @method static \Axcel\Support\Database connection(string $name = null)
 * @method static \Axcel\Support\Database asModel(string|object $model)
 * @method static \PDO getConnection()
 * @method static void setConnection(\PDO $connection)
 *
 * @see \Axcel\Support\Database
 */
class DB extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'database';
    }
}
