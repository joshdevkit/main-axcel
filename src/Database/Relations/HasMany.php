<?php

namespace Axcel\Database\Relations;

use Axcel\Database\Model;

class HasMany
{
    protected Model $related;
    protected Model $parent;
    protected string $foreignKey;
    protected string $localKey;

    // Store additional query constraints added in eager load closures
    protected $queryCallback;

    public function __construct(Model $related, Model $parent, string $foreignKey, string $localKey)
    {
        $this->related = $related;
        $this->parent = $parent;
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
        $this->queryCallback = null;
    }

    public function getQuery()
    {
        // Build a fresh query each time it's called
        $query = $this->related->newQuery()->where($this->foreignKey, $this->parent->getAttribute($this->localKey));

        // Apply any additional query constraints
        if (is_callable($this->queryCallback)) {
            call_user_func($this->queryCallback, $query);
        }

        return $query;
    }

    // Used by your load() method to apply eager load constraints
    public function setQueryCallback(callable $callback)
    {
        $this->queryCallback = $callback;
    }

    public function getResults()
    {
        return $this->getQuery()->get();
    }
}
