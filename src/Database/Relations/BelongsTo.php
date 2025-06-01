<?php

namespace Axcel\Database\Relations;

use Axcel\Database\Model;

class BelongsTo
{
    protected Model $related;
    protected Model $child;
    protected string $foreignKey;
    protected string $ownerKey;

    public function __construct(Model $related, Model $child, string $foreignKey, string $ownerKey)
    {
        $this->related = $related;
        $this->child = $child;
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;
    }

    /**
     * Get the parent (owner) model of the child.
     */
    public function getResults()
    {
        $foreignKeyValue = $this->child->{$this->foreignKey};

        return $this->related->where($this->ownerKey, $foreignKeyValue)->first();
    }
}
