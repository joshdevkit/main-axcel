<?php

namespace Axcel\Database\Traits;

use Axcel\Database\Relations\BelongsTo;
use Axcel\Database\Relations\BelongsToMany;
use Axcel\Database\Relations\HasMany;
use Axcel\Database\Relations\HasOne;

trait HasRelations
{
    /**
     * Define a one-to-one relationship.
     *
     * @param  string  $related
     * @param  string|null  $foreignKey
     * @param  string|null  $localKey
     * @return \Axcel\Database\Relations\HasOne
     */
    public function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null)
    {
        $instance = new $related;

        $foreignKey = $foreignKey ?: strtolower(class_basename($this)) . '_id';
        $localKey = $localKey ?: $this->getKeyName();

        return new HasOne($instance, $this, $foreignKey, $localKey);
    }


    /**
     * Define a one-to-many relationship.
     *
     * @param  string  $related
     * @param  string|null  $foreignKey
     * @param  string|null  $localKey
     * @return \Axcel\Database\Relations\HasMany
     */
    public function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null)
    {
        $instance = new $related;

        $foreignKey = $foreignKey ?: strtolower(class_basename($this)) . '_id';
        $localKey = $localKey ?: $this->getKeyName();

        return new HasMany($instance,  $this, $foreignKey, $localKey);
    }

    /**
     * Define an inverse one-to-one or many relationship.
     *
     * @param  string  $related
     * @param  string|null  $foreignKey
     * @param  string|null  $ownerKey
     * @param  string|null  $relation
     * @return \Axcel\Database\Relations\BelongsTo
     */
    public function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null, ?string $relation = null)
    {
        $instance = new $related;

        $foreignKey = $foreignKey ?: strtolower(class_basename($related)) . '_id';
        $ownerKey = $ownerKey ?: $instance->getKeyName();

        // $relation is the relation name, usually the method name, can be null

        return new BelongsTo($instance, $this, $foreignKey, $ownerKey, $relation);
    }

    /**
     * Define a many-to-many relationship.
     *
     * @param  string  $related
     * @param  string|null  $pivotTable
     * @param  string|null  $foreignPivotKey
     * @param  string|null  $relatedPivotKey
     * @param  string|null  $parentKey
     * @param  string|null  $relatedKey
     * @param  string|null  $relation
     * @return \Axcel\Database\Relations\BelongsToMany
     */
    public function belongsToMany(
        string $related,
        ?string $pivotTable = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null,
        ?string $relation = null
    ) {
        $instance = new $related;

        $table = $pivotTable ?: $this->joiningTable($related);

        $foreignPivotKey = $foreignPivotKey ?: strtolower(class_basename($this)) . '_id';
        $relatedPivotKey = $relatedPivotKey ?: strtolower(class_basename($related)) . '_id';

        $parentKey = $parentKey ?: $this->getKeyName();
        $relatedKey = $relatedKey ?: $instance->getKeyName();

        return new BelongsToMany(
            $instance,
            $this,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $relation
        );
    }

    /**
     * Get the joining table name for many-to-many relationships.
     *
     * @param  string  $related
     * @return string
     */
    protected function joiningTable($related)
    {
        $models = [
            strtolower(class_basename($this)),
            strtolower(class_basename($related))
        ];

        sort($models);

        return implode('_', $models);
    }

    /**
     * Eager load the given relations.
     *
     * @param string|array $relations
     * @return $this|mixed
     */
    // public function load(array|string $relations)
    // {
    //     $relationNames = is_string($relations) ? [$relations] : $relations;

    //     foreach ($relationNames as $key => $value) {
    //         if (is_numeric($key)) {
    //             $relation = $value;
    //             $callback = null;
    //         } else {
    //             $relation = $key;
    //             $callback = $value;
    //         }

    //         if (!method_exists($this, $relation)) {
    //             continue;
    //         }

    //         $relationObject = $this->$relation();

    //         // Apply constraints if a closure is provided
    //         if ($callback instanceof \Closure) {
    //             $callback($relationObject->getQuery());
    //         }

    //         if (
    //             $relationObject instanceof \Axcel\Database\Relations\HasOne ||
    //             $relationObject instanceof \Axcel\Database\Relations\BelongsTo
    //         ) {
    //             $results = $relationObject->getResults() ?? null;
    //         } elseif (
    //             $relationObject instanceof \Axcel\Database\Relations\HasMany ||
    //             $relationObject instanceof \Axcel\Database\Relations\BelongsToMany
    //         ) {
    //             $results = $relationObject->getResults() ?? collect([]);
    //         } else {
    //             continue;
    //         }

    //         $this->setRelation($relation, $results);
    //     }

    //     return $this;
    // }
    public function load(array|string $relations)
    {
        $relationNames = is_string($relations) ? [$relations] : $relations;

        foreach ($relationNames as $key => $value) {
            if (is_numeric($key)) {
                $relation = $value;
                $callback = null;
            } else {
                $relation = $key;
                $callback = $value;
            }

            if (!method_exists($this, $relation)) {
                continue;
            }

            $relationObject = $this->$relation();

            // Instead of calling getQuery(), store the callback for later
            if ($callback instanceof \Closure && method_exists($relationObject, 'setQueryCallback')) {
                $relationObject->setQueryCallback($callback);
            }

            if (
                $relationObject instanceof \Axcel\Database\Relations\HasOne ||
                $relationObject instanceof \Axcel\Database\Relations\BelongsTo
            ) {
                $results = $relationObject->getResults() ?? null;
            } elseif (
                $relationObject instanceof \Axcel\Database\Relations\HasMany ||
                $relationObject instanceof \Axcel\Database\Relations\BelongsToMany
            ) {
                $results = $relationObject->getResults() ?? collect([]);
            } else {
                continue;
            }

            $this->setRelation($relation, $results);
        }

        return $this;
    }



    /**
     * Get a relationship's loaded result.
     *
     * @param string $relationName
     * @return mixed
     */
    public function getRelationValue(string $relationName)
    {
        if (!array_key_exists($relationName, $this->relations)) {
            $this->load($relationName);
        }

        return $this->getRelation($relationName);
    }

    /**
     * Set a relation's value on the model.
     *
     * @param string $relation
     * @param mixed $value
     * @return $this
     */
    public function setRelation($relation, $value)
    {
        $this->relations[$relation] = $value;
        return $this;
    }

    /**
     * Get a relation's value from the model.
     *
     * @param string $key
     * @return mixed
     */
    public function getRelation(string $key): mixed
    {
        return $this->relations[$key] ?? null;
    }
}
