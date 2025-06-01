<?php

namespace Axcel\Database;


use Axcel\Database\Traits\HasAttributes;
use Axcel\Database\Traits\HasRelations;
use Axcel\Database\Traits\HasTimestamps;
use Axcel\Facades\Config;
use Axcel\Support\Collection;
use Axcel\Support\Str;
use JsonSerializable;

abstract class Model implements JsonSerializable
{
    use  HasAttributes, HasTimestamps, HasRelations;

    /**
     * The database connection instance.
     *
     * @var \PDO
     */
    protected $connection;

    /**
     * The database connection name.
     *
     * @var string|null
     */
    protected $connectionName;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the model exists in the database.
     *
     * @var bool
     */
    public $exists = false;


    /**
     * The model's fillable
     * @var array
     */

    protected $fillable = [];

    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * The original attributes of the model.
     *
     * @var array
     */
    protected $original = [];


    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];



    /**
     * The attributes that should be visible in arrays.
     *
     * @var array
     */
    protected $visible = [];

    /**
     * The relationships that should be loaded with the model.
     *
     * @var array
     */
    protected $with = [];


    protected $relations = [];

    /**
     * The database manager instance.
     *
     * @var \Axcel\Database\DatabaseManager
     */
    protected static $resolver;

    /**
     * Create a new model instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array|Model $attributes = [])
    {
        if ($attributes instanceof Model) {
            $attributes = $attributes->toArray();
        }
        $this->fill($attributes);
        $this->table = $this->getTable();
        $this->connectionName = Config::get('database.default');
        $this->connection = static::resolveConnection($this->connectionName);

        // Initialize original as empty for new instances
        $this->original = [];
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array  $attributes
     * @return $this
     */
    public function fill(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return $this
     */
    public function setAttribute($key, $value)
    {
        // Process attribute through HasAttributes trait first
        if (method_exists($this, 'processAttributeBeforeSet')) {
            $processedValue = $this->processAttributeBeforeSet($key, $value);

            // If the value is returned as null, it was handled by a custom setter
            if ($processedValue !== null) {
                $value = $processedValue;
            } else {
                // The attribute was already handled
                return $this;
            }
        }
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Get an attribute from the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        return null;
    }

    /**
     * Get the original value of an attribute.
     *
     * @param  string|null  $key
     * @return mixed
     */
    public function getOriginal($key = null)
    {
        if ($key === null) {
            return $this->original;
        }

        return $this->original[$key] ?? null;
    }

    /**
     * Get the attributes that have been changed since last sync.
     *
     * @return array
     */
    public function getDirty()
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Determine if the model or given attribute(s) have been modified.
     *
     * @param  array|string|null  $attributes
     * @return bool
     */
    public function isDirty($attributes = null)
    {
        $dirty = $this->getDirty();

        if ($attributes === null) {
            return count($dirty) > 0;
        }

        if (!is_array($attributes)) {
            $attributes = [$attributes];
        }

        foreach ($attributes as $attribute) {
            if (array_key_exists($attribute, $dirty)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the model and all the given attribute(s) have remained the same.
     *
     * @param  array|string|null  $attributes
     * @return bool
     */
    public function isClean($attributes = null)
    {
        return !$this->isDirty($attributes);
    }

    /**
     * Sync the original attributes with the current.
     *
     * @return $this
     */
    public function syncOriginal()
    {
        $this->original = $this->attributes;

        return $this;
    }

    /**
     * Get the attributes that should be converted to JSON.
     *
     * @return array
     */
    protected function getArrayableAttributes()
    {
        $attributes = $this->attributes;

        // If visible is set, only show those
        if (count($this->visible) > 0) {
            $attributes = array_intersect_key($attributes, array_flip($this->visible));
        }

        // Hidden takes precedence over visible
        if (count($this->hidden) > 0) {
            $attributes = array_diff_key($attributes, array_flip($this->hidden));
        }

        return $attributes;
    }

    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->getArrayableAttributes();
    }

    /**
     * Convert the model instance to JSON.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }

    public static function query()
    {
        $instance = new static;

        return $instance->newQuery();
    }

    /**
     * Create a new Builder instance for the model.
     *
     * @return \Axcel\Database\Builder
     */
    public function newQuery()
    {
        $query = new QueryBuilder($this->getConnection(), $this->getTable());
        $builder = new Builder($query);

        return $builder->setModel($this);
    }

    /**
     * Create a new model instance.
     *
     * @param  array  $attributes
     * @param  bool  $exists
     * @return static
     */
    public function newInstance($attributes = [], $exists = false)
    {
        $model = new static($attributes);
        $model->exists = $exists;

        return $model;
    }

    /**
     * Create a new model instance from the given array of attributes.
     *
     * @param  array  $attributes
     * @return static
     */
    public function newFromBuilder($attributes = [])
    {
        $model = $this->newInstance($attributes, true);

        // Set original attributes to track changes
        $model->original = $model->attributes;

        return $model;
    }

    /**
     * Create a collection of models from the given array of arrays.
     *
     * @param  array|\Axcel\Support\Collection  $items
     * @return array
     */
    public function hydrate($items)
    {
        if ($items instanceof Collection) {
            $items = $items->toArray();
        }

        $models = [];

        foreach ($items as $item) {
            $models[] = $this->newFromBuilder($item);
        }

        return new \Axcel\Support\Collection($models);
    }


    /**
     * Get the connection for the model.
     *
     * @return \PDO
     */
    public function getConnection()
    {
        return static::resolveConnection($this->connectionName);
    }

    /**
     * Resolve a connection instance.
     *
     * @param  string|null  $connection
     * @return \PDO
     */
    public static function resolveConnection($connection = null)
    {
        return static::$resolver->connection($connection);
    }

    /**
     * Set the connection resolver instance.
     *
     * @param  \Axcel\Database\DatabaseManager  $resolver
     * @return void
     */
    public static function setConnectionResolver(DatabaseManager $resolver)
    {
        static::$resolver = $resolver;
    }

    /**
     * Get the connection resolver instance.
     *
     * @return \Axcel\Database\DatabaseManager
     */
    public static function getConnectionResolver()
    {
        return static::$resolver;
    }

    /**
     * Get the value of the model's primary key.
     *
     * @return mixed
     */
    public function getKey()
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Get the primary key for the model.
     *
     * @return string
     */
    public function getKeyName()
    {
        return $this->primaryKey;
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return $this->table ?? Str::tableize(get_class($this));
    }

    /**
     * Set the table associated with the model.
     *
     * @param  string  $table
     * @return $this
     */
    public function setTable($table)
    {
        if (is_null($this->table)) {
            $this->table = $table;
        };

        return $this;
    }

    /**
     * Get the model keyname and pass to the route
     *
     * @return void
     */
    public function getRouteKeyName()
    {
        return $this->getKeyName();
    }


    /**
     * Get the model's attributes.
     *
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Resolve the route binding for the model.
     *
     * @param  mixed  $value
     * @return static|null
     */
    public function resolveRouteBinding($value)
    {
        return $this->newQuery()->where($this->getRouteKeyName(), $value)->first();
    }

    /**
     * Dynamically retrieve attributes on the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        return $this->attributes[$key] ?? null;
    }


    /**
     * Dynamically set attributes on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Determine if an attribute exists on the model.
     *
     * @param  string  $key
     * @return bool
     */
    public function __isset($key)
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Unset an attribute on the model.
     *
     * @param  string  $key
     * @return void
     */
    public function __unset($key)
    {
        unset($this->attributes[$key]);
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @return mixed data which can be serialized by json_encode
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Handle dynamic static method calls into the method.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return (new static)->$method(...$parameters);
    }

    /**
     * Handle dynamic method calls into the model.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        // Forward the call to a new query builder instance
        return $this->newQuery()->$method(...$parameters);
    }
}
