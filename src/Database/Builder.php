<?php

namespace Axcel\Database;

/**
 * Eloquent query builder for database models.
 *
 * The Builder class provides a fluent interface for building database queries
 * and managing model relationships. It acts as a bridge between the QueryBuilder
 * and Model classes, providing ORM functionality such as eager loading,
 * model hydration, and convenient query methods.
 *
 * Key features:
 * - Fluent query building with method chaining
 * - Eager loading of model relationships via with()
 * - Model creation, updating, and deletion operations
 * - Advanced query methods like firstOrCreate, updateOrCreate, upsert
 * - Pagination support
 * - Custom finder methods and scopes
 *
 * @package Axcel\Database
 * @author Joshua Pacho
 * @version 1.0.0
 * @since 1.0.0
 *
 */

class Builder
{
    /**
     * The model being queried.
     *
     * @var \Axcel\Database\Model
     */
    protected $model;

    /**
     * The query builder instance.
     *
     * @var \Axcel\Database\QueryBuilder
     */
    public $query;


    /**
     * The relationships that should be eager loaded.
     *
     * @var array
     */
    protected $with = [];


    /**
     * Create a new builder instance.
     *
     * @param  \Axcel\Database\QueryBuilder  $query
     * @return void
     */
    public function __construct(QueryBuilder $query)
    {
        $this->query = $query;
    }

    /**
     * Set the model instance being queried.
     *
     * @param  \Axcel\Database\Model  $model
     * @return $this
     */
    public function setModel(Model $model)
    {
        $this->model = $model;
        $this->query->table($model->getTable());

        return $this;
    }

    /**
     * Get the model instance being queried.
     *
     * @return \Axcel\Database\Model
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Set the relationships to be eager loaded.
     *
     * @param  array|string  $relations
     * @return $this
     *
     * @throws \RuntimeException
     */
    public function with($relations)
    {
        $relations = is_array($relations) ? $relations : func_get_args();

        $model = $this->getModel();

        foreach ($relations as $relation) {
            if (!method_exists($model, $relation)) {
                throw new \RuntimeException(
                    "Relation [{$relation}] does not exist on model [" . get_class($model) . "]"
                );
            }
        }

        $this->with = $relations;

        return $this;
    }


    /**
     * Execute a query for a single record by ID.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return \Axcel\Database\Model|null
     */
    public function find($id, $columns = ['*'])
    {
        return $this->where($this->model->getKeyName(), '=', $id)->first($columns);
    }

    /**
     * Execute a query for a single record by ID or throw an exception.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return \Axcel\Database\Model
     * 
     * @throws \RuntimeException
     */
    public function findOrFail($id, $columns = ['*'])
    {
        $result = $this->find($id, $columns);

        if (is_null($result)) {
            throw new \RuntimeException("No results found for model {$id} [{$this->model->getTable()}] ");
        }

        return $result;
    }

    /**
     * Execute the query and get the first result.
     *
     * @param  array  $columns
     * @return \Axcel\Database\Model|null
     */
    public function first($columns = ['*'])
    {
        $result = $this->query->first($columns);
        $model = $result ? $this->model->newFromBuilder($result) : null;

        if ($model && $this->with) {
            $model->load($this->with);
        }

        return $model;
    }

    /**
     * Execute the query and get the first result or throw an exception.
     *
     * @param  array  $columns
     * @return \Axcel\Database\Model
     * 
     * @throws \RuntimeException
     */
    public function firstOrFail($columns = ['*'])
    {
        $result = $this->first($columns);

        if (is_null($result)) {
            throw new \RuntimeException("No query results for model [{$this->model->getTable()}]");
        }

        return $result;
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     * @return \Axcel\Database\Collection
     */
    public function get($columns = ['*'])
    {
        $results = $this->query->get($columns);
        $models = $this->model->hydrate($results);

        if ($this->with) {
            foreach ($models as $model) {
                $model->load($this->with);
            }
        }

        return $models;
    }

    /**
     * Get all of the models from the database.
     *
     * @param  array  $columns
     * @return \Axcel\Support\Collection
     */
    public function all($columns = ['*'])
    {
        // Get raw results from the query builder
        $results = $this->query->get($columns);

        // Hydrate the results into model instances
        return $this->model->hydrate($results);
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

        // If the values is array of arrays, pass it directly
        if (isset($values[0]) && is_array($values[0])) {
            return $this->query->insert($values);
        }

        return $this->query->insert([$values]);
    }

    /**
     * Update records in the database.
     *
     * @param  array  $values
     * @return int
     */
    public function update(array $values)
    {
        $keyName = $this->model->getKeyName();
        $key = $this->model->getKey();

        // Only add "where key = value" if there is no existing where on the key
        $hasKeyWhere = false;
        if (!empty($this->query->wheres)) {
            foreach ($this->query->wheres as $where) {
                if ($where['column'] === $keyName) {
                    $hasKeyWhere = true;
                    break;
                }
            }
        }

        if (!$hasKeyWhere) {
            $this->query->where($keyName, '=', $key);
        }

        return $this->query->update($values);
    }

    /**
     * Delete a specific model instance.
     *
     * @param  \Axcel\Database\Model  $model
     * @return bool
     */
    public function deleteModel(Model $model)
    {
        if (!$model->exists) {
            return false;
        }

        $keyName = $model->getKeyName();
        $key = $model->getKey();

        if (!$key) {
            return false;
        }

        // Create a fresh query builder instance to avoid conflicts
        $query = new QueryBuilder($this->query->connection, $model->getTable());
        $result = $query->where($keyName, '=', $key)->delete();

        if ($result > 0) {
            $model->exists = false;
            return true;
        }

        return false;
    }

    /**
     * Delete records from the database (query builder style).
     *
     * @return int
     */
    public function delete()
    {
        $keyName = $this->model->getKeyName();
        $key = $this->model->getKey();

        $hasKeyWhere = false;

        if (!empty($this->query->wheres)) {
            foreach ($this->query->wheres as $where) {
                if (isset($where['column']) && $where['column'] === $keyName) {
                    $hasKeyWhere = true;
                    break;
                }
            }
        }

        if (!$hasKeyWhere) {
            $this->query->where($keyName, '=', $key);
        }

        return $this->query->delete();
    }


    /**
     * Save a new model and return the instance.
     *
     * @param  array  $attributes
     * @return \Axcel\Database\Model
     */
    public function create(array $attributes = [])
    {
        $model = $this->model->newInstance($attributes);
        $model->save();

        return $model;
    }

    /**
     * Save a model to the database.
     *
     * @param  \Axcel\Database\Model|null  $model
     * @return bool
     */
    public function save(?Model $model = null)
    {
        $model = $model ?? $this->model;

        if ($model === null) {
            throw new \RuntimeException("No model set for the builder. Please set a model using setModel() method.");
        }

        // If the model exists, update it, otherwise insert it
        if ($model->exists) {
            return $this->performUpdate($model);
        }

        return $this->performInsert($model);
    }

    /**
     * Update a specific model instance.
     *
     * @param  \Axcel\Database\Model  $model
     * @param  array  $attributes
     * @return bool
     */
    public function updateModel(Model $model, array $attributes = [])
    {
        if (!$model->exists) {
            return false;
        }

        // Fill the model with new attributes if provided
        if (!empty($attributes)) {
            $model->fill($attributes);
        }

        return $this->performUpdate($model);
    }

    /**
     * Insert the given model into the database.
     *
     * @param  \Axcel\Database\Model  $model
     * @return bool
     */
    protected function performInsert(Model $model)
    {
        // Add timestamps if the model uses them
        if ($model->usesTimestamps()) {
            $model->updateTimestamps();
        }

        $attributes = $model->getAttributes();

        if (empty($attributes)) {
            return true;
        }

        // Create a fresh query builder instance to avoid conflicts
        $query = new QueryBuilder($this->query->connection, $model->getTable());
        $result = $query->insert([$attributes]);

        if ($result) {
            // If the table has auto-incrementing id, set the id on the model
            $id = $query->connection->lastInsertId();
            if ($id) {
                $model->setAttribute($model->getKeyName(), $id);
            }
            $model->exists = true;

            // Mark the model as clean (no dirty attributes)
            $model->syncOriginal();
        }

        return $result;
    }

    /**
     * Update the given model in the database.
     *
     * @param  \Axcel\Database\Model  $model
     * @return bool
     */
    protected function performUpdate(Model $model)
    {
        // Add updated_at timestamp if the model uses timestamps
        if ($model->usesTimestamps()) {
            $model->updateTimestamps();
        }

        // Only get dirty attributes (changed attributes)
        $dirty = $model->getDirty();

        if (empty($dirty)) {
            return true; // No changes to save
        }

        $keyName = $model->getKeyName();
        $key = $model->getKey();

        if (!$key) {
            return false;
        }

        // Create a fresh query builder instance to avoid conflicts
        $query = new QueryBuilder($this->query->connection, $model->getTable());
        $result = $query->where($keyName, '=', $key)->update($dirty);

        if ($result > 0) {
            // Mark the model as clean (sync original attributes)
            $model->syncOriginal();
            return true;
        }

        return false;
    }

    /**
     * Get the first record matching the attributes or create it.
     *
     * @param  array  $attributes
     * @param  array  $values
     * @return \Axcel\Database\Model
     */
    public function firstOrCreate(array $attributes, array $values = [])
    {
        if (!is_null($instance = $this->where($attributes)->first())) {
            return $instance;
        }

        return $this->create(array_merge($attributes, $values));
    }

    /**
     * Get the first record matching the attributes or instantiate it.
     *
     * @param  array  $attributes
     * @param  array  $values
     * @return \Axcel\Database\Model
     */
    public function firstOrNew(array $attributes, array $values = [])
    {
        if (!is_null($instance = $this->where($attributes)->first())) {
            return $instance;
        }

        return $this->model->newInstance(array_merge($attributes, $values));
    }

    /**
     * Create or update a record matching the attributes, and fill it with values.
     *
     * @param  array  $attributes
     * @param  array  $values
     * @return \Axcel\Database\Model
     */
    public function updateOrCreate(array $attributes, array $values = [])
    {
        if (!is_null($instance = $this->where($attributes)->first())) {
            $instance->fill($values);
            $instance->save();
            return $instance;
        }

        return $this->create(array_merge($attributes, $values));
    }

    /**
     * Insert new records or update the existing ones.
     *
     * @param  array  $values
     * @param  array|string  $uniqueBy
     * @param  array|null  $update
     * @return int
     */
    public function upsert(array $values, $uniqueBy, $update = null)
    {
        if (empty($values)) {
            return 0;
        }

        // Ensure values is an array of arrays
        if (!isset($values[0]) || !is_array($values[0])) {
            $values = [$values];
        }

        $uniqueBy = is_array($uniqueBy) ? $uniqueBy : [$uniqueBy];
        $update = $update ?? array_keys(reset($values));

        $affected = 0;

        foreach ($values as $record) {
            $conditions = [];
            foreach ($uniqueBy as $key) {
                if (isset($record[$key])) {
                    $conditions[$key] = $record[$key];
                }
            }

            $updateData = [];
            foreach ($update as $key) {
                if (isset($record[$key]) && !in_array($key, $uniqueBy)) {
                    $updateData[$key] = $record[$key];
                }
            }

            if ($this->where($conditions)->exists()) {
                if (!empty($updateData)) {
                    $affected += $this->where($conditions)->update($updateData);
                }
            } else {
                $this->insert([$record]);
                $affected++;
            }
        }

        return $affected;
    }

    /**
     * Find a model by its primary key or create a new one.
     *
     * @param  mixed  $id
     * @param  array  $attributes
     * @return \Axcel\Database\Model
     */
    public function findOrCreate($id, array $attributes = [])
    {
        if (!is_null($model = $this->find($id))) {
            return $model;
        }

        return $this->create(array_merge($attributes, [$this->model->getKeyName() => $id]));
    }

    /**
     * Find a model by its primary key or return a new instance.
     *
     * @param  mixed  $id
     * @param  array  $attributes
     * @return \Axcel\Database\Model
     */
    public function findOrNew($id, array $attributes = [])
    {
        if (!is_null($model = $this->find($id))) {
            return $model;
        }

        return $this->model->newInstance(array_merge($attributes, [$this->model->getKeyName() => $id]));
    }

    /**
     * Execute the query and get the first result or create a new instance.
     *
     * @param  array  $attributes
     * @return \Axcel\Database\Model
     */
    public function firstOrInit(array $attributes = [])
    {
        if (!is_null($instance = $this->first())) {
            return $instance;
        }

        return $this->model->newInstance($attributes);
    }

    /**
     * Create a collection of models from the given array.
     *
     * @param  array  $records
     * @return \Axcel\Support\Collection
     */
    public function createMany(array $records)
    {
        $models = [];

        foreach ($records as $record) {
            $models[] = $this->create($record);
        }

        return new \Axcel\Support\Collection($models);
    }

    /**
     * Insert records and ignore duplicates.
     *
     * @param  array  $values
     * @return bool
     */
    public function insertOrIgnore(array $values)
    {
        if (empty($values)) {
            return true;
        }

        // Ensure values is an array of arrays
        if (!isset($values[0]) || !is_array($values[0])) {
            $values = [$values];
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

        $sql = "INSERT IGNORE INTO {$this->model->getTable()} ({$columnsString}) VALUES " . implode(', ', $placeholders);

        try {
            $stmt = $this->query->connection->prepare($sql);
            return $stmt->execute($bindings);
        } catch (\PDOException $e) {
            // For databases that don't support INSERT IGNORE, fall back to regular insert
            // and catch duplicate entry exceptions
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                return true; // Ignore duplicate entries
            }
            throw $e;
        }
    }

    /**
     * Paginate the given query.
     *
     * @param  int  $perPage
     * @param  array  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Axcel\Database\Paginator
     */
    public function paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null)
    {
        $page = $page ?: (isset($_GET[$pageName]) ? (int) $_GET[$pageName] : 1);
        $total = $this->query->count();
        $this->query->forPage($page, $perPage);
        $items = $this->get($columns);
        return new Paginator($items, $total, $perPage, $page);
    }

    /**
     * Dynamically handle calls into the query instance.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        // Forward the call to the query builder
        $result = $this->query->{$method}(...$parameters);

        // If the method is a query builder method that should return $this
        $builderMethods = [
            'select',
            'where',
            'orWhere',
            'whereIn',
            'whereNotIn',
            'whereNull',
            'whereNotNull',
            'join',
            'leftJoin',
            'rightJoin',
            'orderBy',
            'orderByDesc',
            'groupBy',
            'having',
            'skip',
            'take',
            'limit',
            'offset',
            'forPage'
        ];

        if (in_array($method, $builderMethods)) {
            return $this;
        }

        return $result;
    }

    /**
     * Get a single record by email.
     *
     * @param  string  $email
     * @return \Axcel\Database\Model
     * 
     * @throws \RuntimeException
     */
    public function whereEmail($email)
    {
        $result = $this->where('email', '=', $email)->first();
        if (is_null($result)) {
            throw new \RuntimeException("No results found for model " . ucfirst($this->model->getTable()) . " with email {$email}");
        }

        return $result;
    }

    /**
     * Find a record by a column or fail.
     *
     * @param  string  $column
     * @param  mixed   $value
     * @return \Axcel\Database\Model
     * 
     * @throws \RuntimeException
     */
    public function findOrFailBy(string $column, $value)
    {
        $result = $this->where($column, '=', $value)->first();
        if (is_null($result)) {
            throw new \RuntimeException("No results found for {$column} = {$value}.");
        }
        return $result;
    }

    /**
     * Get only active records.
     *
     * @return \Axcel\Support\Collection
     */
    public function onlyActive()
    {
        return $this->where('is_active', '=', 1)->get();
    }

    /**
     * Get all records excluding the authenticated user.
     *
     * @return \Axcel\Support\Collection
     */
    public function excludeAuth()
    {
        if (auth()->check()) {
            return $this->where($this->model->getKeyName(), '!=', auth()->id());
        }
        return $this;
    }
}
