<?php

namespace Axcel\Database\Schema\Definitions;

class ForeignKeyDefinition
{
    protected array $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function get(string $key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Get all attributes
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get a specific attribute
     */
    public function getAttribute(string $key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }

    public function getReferences(): array
    {
        return $this->attributes['references'] ?? [];
    }
    /**
     * Set an attribute
     */
    public function setAttribute(string $key, $value): self
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * Get the columns that make up the foreign key
     */
    public function getColumns(): array
    {
        return $this->attributes['columns'] ?? [];
    }

    /**
     * Get the referenced table
     */
    public function getReferencedTable(): ?string
    {
        return $this->attributes['referencedTable'] ?? null;
    }

    /**
     * Get the referenced columns
     */
    public function getReferencedColumns(): array
    {
        return $this->attributes['referencedColumns'] ?? [];
    }

    /**
     * Get the constraint name
     */
    public function getName(): ?string
    {
        return $this->attributes['name'] ?? null;
    }

    /**
     * Set the constraint name
     */
    public function name(string $name): self
    {
        $this->attributes['name'] = $name;
        return $this;
    }

    /**
     * Specify the referenced table and columns
     */
    public function references(string|array $columns): self
    {
        $this->attributes['referencedColumns'] = (array) $columns;
        return $this;
    }

    /**
     * Specify the referenced table
     */
    public function on(string $table): self
    {
        $this->attributes['referencedTable'] = $table;
        return $this;
    }

    /**
     * Set the ON DELETE action
     */
    public function onDelete(string $action): self
    {
        $this->validateAction($action);
        $this->attributes['onDelete'] = strtoupper($action);
        return $this;
    }

    /**
     * Set the ON UPDATE action
     */
    public function onUpdate(string $action): self
    {
        $this->validateAction($action);
        $this->attributes['onUpdate'] = strtoupper($action);
        return $this;
    }

    /**
     * Set CASCADE on delete
     */
    public function cascadeOnDelete(): self
    {
        return $this->onDelete('cascade');
    }

    /**
     * Set RESTRICT on delete
     */
    public function restrictOnDelete(): self
    {
        return $this->onDelete('restrict');
    }

    /**
     * Set NULL on delete
     */
    public function nullOnDelete(): self
    {
        return $this->onDelete('set null');
    }

    /**
     * Set NO ACTION on delete
     */
    public function noActionOnDelete(): self
    {
        return $this->onDelete('no action');
    }

    /**
     * Set CASCADE on update
     */
    public function cascadeOnUpdate(): self
    {
        return $this->onUpdate('cascade');
    }

    /**
     * Set RESTRICT on update
     */
    public function restrictOnUpdate(): self
    {
        return $this->onUpdate('restrict');
    }

    /**
     * Set NULL on update
     */
    public function nullOnUpdate(): self
    {
        return $this->onUpdate('set null');
    }

    /**
     * Set NO ACTION on update
     */
    public function noActionOnUpdate(): self
    {
        return $this->onUpdate('no action');
    }

    /**
     * Make the foreign key deferrable
     */
    public function deferrable(bool $value = true): self
    {
        $this->attributes['deferrable'] = $value;
        return $this;
    }

    /**
     * Make the foreign key initially deferred
     */
    public function initiallyImmediate(): self
    {
        $this->attributes['initiallyImmediate'] = true;
        return $this;
    }

    /**
     * Make the foreign key initially deferred
     */
    public function initiallyDeferred(): self
    {
        $this->attributes['initiallyDeferred'] = true;
        return $this;
    }

    /**
     * Validate the referential action
     */
    protected function validateAction(string $action): void
    {
        $validActions = [
            'restrict',
            'cascade',
            'set null',
            'no action',
            'set default'
        ];

        if (!in_array(strtolower($action), $validActions)) {
            throw new \InvalidArgumentException("Invalid foreign key action: {$action}");
        }
    }

    /**
     * Get the ON DELETE action
     */
    public function getOnDelete(): ?string
    {
        return $this->attributes['onDelete'] ?? null;
    }

    /**
     * Get the ON UPDATE action
     */
    public function getOnUpdate(): ?string
    {
        return $this->attributes['onUpdate'] ?? null;
    }

    /**
     * Check if the foreign key is deferrable
     */
    public function isDeferrable(): bool
    {
        return $this->attributes['deferrable'] ?? false;
    }

    /**
     * Check if the foreign key is initially immediate
     */
    public function isInitiallyImmediate(): bool
    {
        return $this->attributes['initiallyImmediate'] ?? false;
    }

    /**
     * Check if the foreign key is initially deferred
     */
    public function isInitiallyDeferred(): bool
    {
        return $this->attributes['initiallyDeferred'] ?? false;
    }

    /**
     * Convert to array for serialization
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Generate a default constraint name
     */
    public function generateName(string $table): string
    {
        if ($this->getName()) {
            return $this->getName();
        }

        $referencedTable = $this->getReferencedTable();
        $columns = implode('_', $this->getColumns());

        return strtolower("{$table}_{$columns}_foreign");
    }

    /**
     * String representation for debugging
     */
    public function __toString(): string
    {
        return json_encode($this->attributes);
    }

    /**
     * Validate the foreign key definition
     */
    public function validate(): void
    {
        if (empty($this->getColumns())) {
            throw new \InvalidArgumentException('Foreign key must have at least one column');
        }

        if (empty($this->getReferencedTable())) {
            throw new \InvalidArgumentException('Foreign key must reference a table');
        }

        if (empty($this->getReferencedColumns())) {
            throw new \InvalidArgumentException('Foreign key must reference at least one column');
        }

        if (count($this->getColumns()) !== count($this->getReferencedColumns())) {
            throw new \InvalidArgumentException('Foreign key column count must match referenced column count');
        }
    }

    /**
     * Check if the foreign key definition is complete
     */
    public function isComplete(): bool
    {
        try {
            $this->validate();
            return true;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }
}
