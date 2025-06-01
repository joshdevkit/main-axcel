<?php

namespace Axcel\Database\Relations;

use Axcel\Database\Model;
use Axcel\Facades\DB;
use Axcel\Support\Collection;

class BelongsToMany
{
    protected Model $related;
    protected Model $parent;
    protected string $table;              // pivot table name
    protected string $foreignPivotKey;   // foreign key on pivot table referencing parent
    protected string $relatedPivotKey;   // foreign key on pivot table referencing related model
    protected string $parentKey;          // primary key on parent model
    protected string $relatedKey;         // primary key on related model

    public function __construct(
        Model $related,
        Model $parent,
        string $table,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey,
        string $relatedKey
    ) {
        $this->related = $related;
        $this->parent = $parent;
        $this->table = $table;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->relatedPivotKey = $relatedPivotKey;
        $this->parentKey = $parentKey;
        $this->relatedKey = $relatedKey;
    }

    /**
     * Get the related models for the parent model via the pivot table.
     *
     * @return \Axcel\Support\Collection<Model>
     */
    public function getResults(): Collection
    {
        // 1. Get the parent key value (e.g., user id)
        $parentKeyValue = $this->parent->{$this->parentKey};

        // 2. Get related IDs from the pivot table
        $relatedIds = $this->getRelatedIdsFromPivot($parentKeyValue);

        // If no related IDs, return empty array
        if (empty($relatedIds)) {
            return new Collection();
        }

        // 3. Query the related models with whereIn on their primary key
        return $this->related->whereIn($this->relatedKey, $relatedIds)->get();
    }

    public function get(): \Axcel\Support\Collection
    {
        return $this->getResults();
    }


    /**
     * Query the pivot table to get related model IDs
     * @param mixed $parentKeyValue
     * @return array Related model IDs
     */
    protected function getRelatedIdsFromPivot($parentKeyValue): array
    {
        $query = "SELECT {$this->relatedPivotKey} FROM {$this->table} WHERE {$this->foreignPivotKey} = ?";
        $bindings = [$parentKeyValue];

        // Use your DB helper to run the select query
        $results = DB::select($query, $bindings);

        // Map results to an array of IDs
        return array_map(fn($row) => $row[$this->relatedPivotKey], $results);
    }


    public function contains(string $key, mixed $value): bool
    {
        return $this->getResults()->contains($key, $value);
    }
}
