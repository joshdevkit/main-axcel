<?php

namespace Axcel\Database\Relations;

use Axcel\Database\Model;
use Axcel\Database\Builder;

/**
 * Base relation class (e.g., HasOne, BelongsTo) that does **not** override or implement 
 * any model-level method functionality such as create, update, or delete.
 * 
 * These classes are solely responsible for defining the relationship logic 
 * and should not be used to perform CRUD operations directly.
 * Follows SOLID principles and the DRY principle by keeping responsibilities separated 
 * and avoiding code duplication within model-relation logic.
 */

class HasOne
{
    protected Model $parent;
    protected Model $related;
    protected string $foreignKey;
    protected string $localKey;
    protected ?Builder $query = null;

    public function __construct(Model $related, Model $parent, string $foreignKey, string $localKey)
    {
        $this->related = $related;
        $this->parent = $parent;
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;

        $this->query = $this->related->query()
            ->where($this->foreignKey, '=', $this->parent->getAttribute($this->localKey));
    }

    /**
     * Get the query builder for this relationship
     */
    public function getQuery(): Builder
    {
        return $this->query;
    }

    /**
     * Get the related model instance
     */
    public function getResults()
    {
        return $this->query->first();
    }

    /**
     * Check if the related model exists
     */
    public function exists(): bool
    {
        return $this->query->count() > 0;
    }

    /**
     * Count the related models (should be 0 or 1 for HasOne)
     */
    public function count(): int
    {
        return $this->query->count();
    }

    /**
     * Get the foreign key name
     */
    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    /**
     * Get the local key name
     */
    public function getLocalKeyName(): string
    {
        return $this->localKey;
    }

    /**
     * Get the parent key value
     */
    public function getParentKeyValue()
    {
        return $this->parent->getAttribute($this->localKey);
    }

    /**
     * Get the related model class
     */
    public function getRelated(): Model
    {
        return $this->related;
    }

    /**
     * Get the parent model
     */
    public function getParent(): Model
    {
        return $this->parent;
    }

    /**
     * Add additional constraints to the relationship query
     */
    public function where($column, $operator = null, $value = null): Builder
    {
        return $this->query->where($column, $operator, $value);
    }

    /**
     * Add additional constraints to the relationship query
     */
    public function whereIn($column, $values): Builder
    {
        return $this->query->whereIn($column, $values);
    }

    /**
     * Order the relationship query
     */
    public function orderBy($column, $direction = 'asc'): Builder
    {
        return $this->query->orderBy($column, $direction);
    }
}
