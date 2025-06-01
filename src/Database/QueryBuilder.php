<?php

namespace Axcel\Database;

use Axcel\Database\Traits\WhereCompilers;

class QueryBuilder
{
    use WhereCompilers;
    /**
     * The database connection.
     *
     * @var \PDO
     */
    public $connection;

    /**
     * The table which the query is targeting.
     *
     * @var string
     */
    protected $table;

    /**
     * The where constraints for the query.
     *
     * @var array
     */
    public $wheres = [];

    /**
     * The columns that should be returned.
     *
     * @var array
     */
    protected $columns = ['*'];

    /**
     * The orderings for the query.
     *
     * @var array
     */
    protected $orders = [];

    /**
     * The maximum number of records to return.
     *
     * @var int|null
     */
    protected $limit;

    /**
     * The number of records to skip.
     *
     * @var int|null
     */
    protected $offset;

    /**
     * The joins for the query.
     *
     * @var array
     */
    protected $joins = [];

    /**
     * The query groupings.
     *
     * @var array
     */
    protected $groups = [];

    /**
     * The having constraints for the query.
     *
     * @var array
     */
    protected $havings = [];


    /**
     * The UNION queries.
     *
     * @var array
     */
    protected $unions = [];

    /**
     * Indicates if the query should use DISTINCT.
     *
     * @var bool
     */
    protected $distinct = false;


    /**
     * The bindings for the query.
     *
     * @var array
     */
    public $bindings = [
        'where' => [],
        'join' => [],
        'having' => []
    ];

    /**
     * Create a new query builder instance.
     *
     * @param  \PDO  $connection
     * @param  string|null  $table
     * @return void
     */
    public function __construct(\PDO $connection, $table = null)
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    /**
     * Set the table which the query is targeting.
     *
     * @param  string  $table
     * @return $this
     */
    public function table($table)
    {
        $this->table = $table;

        return $this;
    }

    /**
     * Set the columns to be selected.
     *
     * @param  array|string  $columns
     * @return $this
     */
    public function select($columns = ['*'])
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();

        return $this;
    }


    /**
     * Add a basic where clause to the query.
     *
     * @param  string|array|\Closure  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        // If the column is an array, we assume it's a key-value pair
        if (is_array($column)) {
            foreach ($column as $key => $value) {
                $this->where($key, '=', $value, $boolean);
            }

            return $this;
        }

        // If only two arguments are passed, we assume the operator is '='
        if (func_num_args() === 2) {
            list($value, $operator) = [$operator, '='];
        }

        $type = 'basic';

        $this->wheres[] = compact('type', 'column', 'operator', 'value', 'boolean');
        $this->bindings['where'][] = $value;

        return $this;
    }

    /**
     * Add a raw where clause to the query.
     *
     * @param string $sql Raw SQL where clause (with placeholders)
     * @param array $bindings Bindings for the placeholders
     * @return $this
     */
    public function whereRaw(string $sql, array $bindings = [])
    {
        $this->wheres[] = [
            'type' => 'raw',
            'sql' => $sql,
            'bindings' => $bindings,
        ];

        return $this;
    }

    /**
     * Add an "or where" clause to the query.
     *
     * @param  string|array|\Closure  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function orWhere($column, $operator = null, $value = null)
    {
        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * Add a "where in" clause to the query.
     *
     * @param  string  $column
     * @param  array  $values
     * @param  string  $boolean
     * @return $this
     */
    public function whereIn($column, array $values, $boolean = 'and')
    {
        $type = 'in';

        $this->wheres[] = compact('type', 'column', 'values', 'boolean');

        // Add each value to the bindings
        foreach ($values as $value) {
            $this->bindings['where'][] = $value;
        }

        return $this;
    }

    /**
     * Add a "where not in" clause to the query.
     *
     * @param  string  $column
     * @param  array  $values
     * @param  string  $boolean
     * @return $this
     */
    public function whereNotIn($column, array $values, $boolean = 'and')
    {
        $type = 'notIn';

        $this->wheres[] = compact('type', 'column', 'values', 'boolean');

        // Add each value to the bindings
        foreach ($values as $value) {
            $this->bindings['where'][] = $value;
        }

        return $this;
    }

    /**
     * Add a "where null" clause to the query.
     *
     * @param  string  $column
     * @param  string  $boolean
     * @return $this
     */
    public function whereNull($column, $boolean = 'and')
    {
        $type = 'null';

        $this->wheres[] = compact('type', 'column', 'boolean');

        return $this;
    }

    /**
     * Add a "where not null" clause to the query.
     *
     * @param  string  $column
     * @param  string  $boolean
     * @return $this
     */
    public function whereNotNull($column, $boolean = 'and')
    {
        $type = 'notNull';

        $this->wheres[] = compact('type', 'column', 'boolean');

        return $this;
    }

    /**
     * Add an "or where in" clause to the query.
     *
     * @param  string  $column
     * @param  array  $values
     * @return $this
     */
    public function orWhereIn($column, array $values)
    {
        return $this->whereIn($column, $values, 'or');
    }

    /**
     * Add an "or where not in" clause to the query.
     *
     * @param  string  $column
     * @param  array  $values
     * @return $this
     */
    public function orWhereNotIn($column, array $values)
    {
        return $this->whereNotIn($column, $values, 'or');
    }

    /**
     * Add an "or where null" clause to the query.
     *
     * @param  string  $column
     * @return $this
     */
    public function orWhereNull($column)
    {
        return $this->whereNull($column, 'or');
    }

    /**
     * Add an "or where not null" clause to the query.
     *
     * @param  string  $column
     * @return $this
     */
    public function orWhereNotNull($column)
    {
        return $this->whereNotNull($column, 'or');
    }

    /**
     * Add a "where date" clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    public function whereDate($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (func_num_args() === 2) {
            list($value, $operator) = [$operator, '='];
        }

        $type = 'date';
        $this->wheres[] = compact('type', 'column', 'operator', 'value', 'boolean');
        $this->bindings['where'][] = $value;

        return $this;
    }

    /**
     * Add a "where year" clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    public function whereYear($column, $value, $boolean = 'and')
    {
        $type = 'year';
        $this->wheres[] = compact('type', 'column', 'value', 'boolean');
        $this->bindings['where'][] = $value;

        return $this;
    }

    /**
     * Add a "where month" clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    public function whereMonth($column, $value, $boolean = 'and')
    {
        $type = 'month';
        $this->wheres[] = compact('type', 'column', 'value', 'boolean');
        $this->bindings['where'][] = $value;

        return $this;
    }

    /**
     * Add a "where between" clause to the query.
     *
     * @param  string  $column
     * @param  array  $values
     * @param  string  $boolean
     * @return $this
     */
    public function whereBetween($column, array $values, $boolean = 'and')
    {
        $type = 'between';
        $this->wheres[] = compact('type', 'column', 'values', 'boolean');

        foreach ($values as $value) {
            $this->bindings['where'][] = $value;
        }

        return $this;
    }

    /**
     * Add a "where not between" clause to the query.
     *
     * @param  string  $column
     * @param  array  $values
     * @param  string  $boolean
     * @return $this
     */
    public function whereNotBetween($column, array $values, $boolean = 'and')
    {
        $type = 'notBetween';
        $this->wheres[] = compact('type', 'column', 'values', 'boolean');

        foreach ($values as $value) {
            $this->bindings['where'][] = $value;
        }

        return $this;
    }

    /**
     * Add a "where like" clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    public function whereLike($column, $value, $boolean = 'and')
    {
        return $this->where($column, 'LIKE', $value, $boolean);
    }

    /**
     * Add a "where not like" clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    public function whereNotLike($column, $value, $boolean = 'and')
    {
        return $this->where($column, 'NOT LIKE', $value, $boolean);
    }

    /**
     * Add an "or where like" clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $value
     * @return $this
     */
    public function orWhereLike($column, $value)
    {
        return $this->whereLike($column, $value, 'or');
    }

    /**
     * Apply the callback if the given "value" is truthy.
     *
     * @param  mixed  $value
     * @param  callable  $callback
     * @param  callable|null  $default
     * @return $this
     */
    public function when($value, callable $callback, ?callable $default = null)
    {
        if ($value) {
            return $callback($this, $value) ?: $this;
        } elseif ($default) {
            return $default($this, $value) ?: $this;
        }

        return $this;
    }

    /**
     * Apply the callback if the given "value" is falsy.
     *
     * @param  mixed  $value
     * @param  callable  $callback
     * @param  callable|null  $default
     * @return $this
     */
    public function unless($value, callable $callback, ?callable $default = null)
    {
        if (!$value) {
            return $callback($this, $value) ?: $this;
        } elseif ($default) {
            return $default($this, $value) ?: $this;
        }

        return $this;
    }

    /**
     * Pass the query to a given callback.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function tap(callable $callback)
    {
        $callback($this);
        return $this;
    }

    /**
     * Clone the query builder.
     *
     * @return static
     */
    public function clone()
    {
        return clone $this;
    }

    /**
     * Count records with a WHERE condition.
     *
     * @param  string  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return int
     */
    public function countWhere($column, $operator = null, $value = null)
    {
        return $this->where($column, $operator, $value)->count();
    }

    /**
     * Sum column values with a WHERE condition.
     *
     * @param  string  $column
     * @param  string  $whereColumn
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return mixed
     */
    public function sumWhere($column, $whereColumn, $operator = null, $value = null)
    {
        return $this->where($whereColumn, $operator, $value)->sum($column);
    }

    /**
     * Add a UNION clause to the query.
     *
     * @param  QueryBuilder  $query
     * @return $this
     */
    public function union(QueryBuilder $query)
    {
        $this->unions[] = $query;
        return $this;
    }

    /**
     * Add DISTINCT to the SELECT clause.
     *
     * @return $this
     */
    public function distinct()
    {
        $this->distinct = true;
        return $this;
    }

    /**
     * Add an "order by" clause for the latest records.
     *
     * @param  string  $column
     * @return $this
     */
    public function latest($column = 'created_at')
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Add an "order by" clause for the oldest records.
     *
     * @param  string  $column
     * @return $this
     */
    public function oldest($column = 'created_at')
    {
        return $this->orderBy($column, 'asc');
    }

    /**
     * Add a join clause to the query.
     *
     * @param  string  $table
     * @param  string  $first
     * @param  string  $operator
     * @param  string  $second
     * @param  string  $type
     * @return $this
     */
    public function join($table, $first, $operator = null, $second = null, $type = 'inner')
    {
        $join = [
            'table' => $table,
            'type' => $type,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];

        $this->joins[] = $join;

        return $this;
    }

    /**
     * Add a left join to the query.
     *
     * @param  string  $table
     * @param  string  $first
     * @param  string  $operator
     * @param  string  $second
     * @return $this
     */
    public function leftJoin($table, $first, $operator = null, $second = null)
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    /**
     * Add a right join to the query.
     *
     * @param  string  $table
     * @param  string  $first
     * @param  string  $operator
     * @param  string  $second
     * @return $this
     */
    public function rightJoin($table, $first, $operator = null, $second = null)
    {
        return $this->join($table, $first, $operator, $second, 'right');
    }

    /**
     * Add an order by clause to the query.
     *
     * @param  string  $column
     * @param  string  $direction
     * @return $this
     */
    public function orderBy($column, $direction = 'asc')
    {
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        $this->orders[] = compact('column', 'direction');

        return $this;
    }

    /**
     * Add a descending order by clause to the query.
     *
     * @param  string  $column
     * @return $this
     */
    public function orderByDesc($column)
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Add a group by clause to the query.
     *
     * @param  array|string  $columns
     * @return $this
     */
    public function groupBy($columns)
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        foreach ($columns as $column) {
            $this->groups[] = $column;
        }

        return $this;
    }

    /**
     * Add a having clause to the query.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    public function having($column, $operator = null, $value = null, $boolean = 'and')
    {
        // If only two arguments are passed, we'll assume '='
        if (func_num_args() === 2) {
            list($value, $operator) = [$operator, '='];
        }

        $type = 'basic';

        $this->havings[] = compact('type', 'column', 'operator', 'value', 'boolean');
        $this->bindings['having'][] = $value;

        return $this;
    }

    /**
     * Set the limit and offset for a given page.
     *
     * @param  int  $page
     * @param  int  $perPage
     * @return $this
     */
    public function forPage($page, $perPage = 15)
    {
        return $this->skip(($page - 1) * $perPage)->take($perPage);
    }

    /**
     * Alias to set the "limit" value of the query.
     *
     * @param  int  $value
     * @return $this
     */
    public function take($value)
    {
        return $this->limit($value);
    }

    /**
     * Set the "limit" value of the query.
     *
     * @param  int  $value
     * @return $this
     */
    public function limit($value)
    {
        $this->limit = $value;

        return $this;
    }

    /**
     * Alias to set the "offset" value of the query.
     *
     * @param  int  $value
     * @return $this
     */
    public function skip($value)
    {
        return $this->offset($value);
    }

    /**
     * Set the "offset" value of the query.
     *
     * @param  int  $value
     * @return $this
     */
    public function offset($value)
    {
        $this->offset = $value;

        return $this;
    }

    /**
     * Get a single record from the database.
     *
     * @param  array  $columns
     * @return mixed
     */
    public function first($columns = ['*'])
    {
        $results = $this->limit(1)->get($columns);
        return count($results) ? $results[0] : null;
    }


    /**
     * Execute the query and get the first result or throw an exception.
     *
     * @param  array  $columns
     * @return mixed
     * 
     * @throws \RuntimeException
     */
    public function firstOrFail($columns = ['*'])
    {
        $result = $this->first($columns);

        if (is_null($result)) {
            throw new \RuntimeException('No query results for model.');
        }

        return $result;
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     * @return array
     */
    public function get($columns = ['*'])
    {
        if (!empty($columns) && $columns !== ['*']) {
            $this->columns = is_array($columns) ? $columns : func_get_args();
        }

        $sql = $this->toSql();
        $stmt = $this->connection->prepare($sql);

        $this->bindValues($stmt);
        $stmt->execute();

        return  $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }


    public function all($columns = ['*'])
    {
        return  $this->get($columns);
    }

    /**
     * Insert a new record into the database.
     *
     * @param  array  $values
     * @return bool
     */
    public function insert(array $values)
    {
        if (empty($values)) {
            return true;
        }

        $columns = array_keys(reset($values));
        $bindings = [];

        // Build the columns part of the SQL statement
        $columnsString = implode(', ', $columns);

        // Create the placeholders and collect the bindings
        $placeholders = [];
        foreach ($values as $record) {
            $recordPlaceholders = [];
            foreach ($columns as $column) {
                $recordPlaceholders[] = '?';
                $bindings[] = $record[$column] ?? null;
            }
            $placeholders[] = '(' . implode(', ', $recordPlaceholders) . ')';
        }

        $sql = "INSERT INTO {$this->table} ({$columnsString}) VALUES " . implode(', ', $placeholders);

        $stmt = $this->connection->prepare($sql);

        return $stmt->execute($bindings);
    }

    /**
     * Update records in the database.
     *
     * @param  array  $values
     * @return int
     */
    public function update(array $values)
    {
        if (empty($values)) {
            return 0;
        }

        $sets = [];
        $bindings = [];

        // Build SET clause
        foreach ($values as $column => $value) {
            $sets[] = "{$column} = ?";
            $bindings[] = $value;
        }

        // Merge WHERE bindings after SET bindings
        $bindings = array_merge($bindings, $this->bindings['where'] ?? []);

        // Compile WHERE clause
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . $this->compileWheres();

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->rowCount();
    }


    /**
     * Delete records from the database.
     *
     * @return int
     */
    public function delete()
    {
        $sql = "DELETE FROM {$this->table}" . $this->compileWheres();
        $stmt = $this->connection->prepare($sql);
        $bindings = $this->bindings['where'] ?? [];
        $stmt->execute($bindings);

        return $stmt->rowCount();
    }


    /**
     * Determine if any rows exist for the current query.
     *
     * @return bool
     */
    public function exists()
    {
        $results = $this->connection->query("SELECT EXISTS(
            SELECT 1 FROM {$this->table}" . $this->compileWheres() . " LIMIT 1
        ) as exists_result")->fetchAll(\PDO::FETCH_ASSOC);

        return (bool) $results[0]['exists_result'];
    }

    /**
     * Retrieve the "count" result of the query.
     *
     * @param  string  $columns
     * @return int
     */
    public function count($column = '*')
    {
        return (int) $this->aggregate('count', $column);
    }

    /**
     * Retrieve the minimum value of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function min($column)
    {
        return $this->aggregate('min', $column);
    }

    /**
     * Retrieve the maximum value of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function max($column)
    {
        return $this->aggregate('max', $column);
    }

    /**
     * Retrieve the sum of the values of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function sum($column)
    {
        return $this->aggregate('sum', $column);
    }

    /**
     * Retrieve the average of the values of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function avg($column)
    {
        return $this->aggregate('avg', $column);
    }

    /**
     * Execute an aggregate function on the database.
     *
     * @param  string  $function
     * @param  string  $column
     * @return mixed
     */
    protected function aggregate($function, $column)
    {
        $sql = "SELECT {$function}({$column}) as aggregate FROM {$this->table}" . $this->compileWheres();

        $stmt = $this->connection->prepare($sql);

        // Use bindValues method to bind all parameters correctly
        $this->bindValues($stmt);

        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result['aggregate'];
    }

    /**
     * Bind values to the prepared statement.
     *
     * @param  \PDOStatement  $statement
     * @return void
     */
    protected function bindValues($statement)
    {
        $index = 1;

        foreach ($this->bindings as $type => $bindings) {
            foreach ($bindings as $value) {
                $statement->bindValue($index++, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }
        }
    }

    /**
     * Get the SQL representation of the query.
     *
     * @return string
     */
    public function toSql()
    {
        $sql = "SELECT " . implode(', ', $this->columns) . " FROM {$this->table}";

        // Add joins
        foreach ($this->joins as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }

        // Add where clauses
        $sql .= $this->compileWheres();

        // Add group by
        if (!empty($this->groups)) {
            $sql .= " GROUP BY " . implode(', ', $this->groups);
        }

        // Add having clauses
        if (!empty($this->havings)) {
            $sql .= $this->compileHavings();
        }

        // Add order by
        if (!empty($this->orders)) {
            $orderClauses = [];
            foreach ($this->orders as $order) {
                $orderClauses[] = "{$order['column']} {$order['direction']}";
            }
            $sql .= " ORDER BY " . implode(', ', $orderClauses);
        }

        // Add limit
        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        // Add offset
        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        return $sql;
    }

    /**
     * Get the current query value bindings.
     *
     * @return array
     */
    public function getBindings()
    {
        return array_merge(
            $this->bindings['where'],
            $this->bindings['join'],
            $this->bindings['having']
        );
    }

    /**
     * Compile the having clauses.
     *
     * @return string
     */
    protected function compileHavings()
    {
        if (empty($this->havings)) {
            return '';
        }

        $sql = ' HAVING ';
        $clauses = [];

        foreach ($this->havings as $i => $having) {
            $boolean = $i === 0 ? '' : " {$having['boolean']} ";

            if ($having['type'] === 'basic') {
                $clauses[] = $boolean . "{$having['column']} {$having['operator']} ?";
            }
        }

        return $sql . implode('', $clauses);
    }

    /**
     * Reset the query builder properties.
     *
     * @return void
     */
    protected function reset()
    {
        $this->columns = ['*'];
        $this->wheres = [];
        $this->bindings = [
            'where' => [],
            'join' => [],
            'having' => []
        ];
        $this->orders = [];
        $this->limit = null;
        $this->offset = null;
        $this->joins = [];
        $this->groups = [];
        $this->havings = [];
    }
}
