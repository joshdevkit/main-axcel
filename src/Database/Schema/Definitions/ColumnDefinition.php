<?php

namespace Axcel\Database\Schema\Definitions;

use Axcel\Database\Schema\Blueprint;

class ColumnDefinition
{
    protected array $attributes = [];
    protected Blueprint $blueprint;

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function get(string $key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Set the blueprint reference
     */
    public function setBlueprint(Blueprint $blueprint): self
    {
        $this->blueprint = $blueprint;
        return $this;
    }

    /**
     * Get column name
     */
    public function getName(): string
    {
        return $this->attributes['name'] ?? '';
    }

    /**
     * Get column type
     */
    public function getType(): string
    {
        return $this->attributes['type'] ?? '';
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

    /**
     * Set an attribute
     */
    public function setAttribute(string $key, $value): self
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    // Column modifiers
    public function nullable(bool $value = true): self
    {
        $this->attributes['nullable'] = $value;
        return $this;
    }

    public function default($value): self
    {
        $this->attributes['default'] = $value;
        return $this;
    }

    public function unsigned(): self
    {
        $this->attributes['unsigned'] = true;
        return $this;
    }

    public function autoIncrement(): self
    {
        $this->attributes['autoIncrement'] = true;
        return $this;
    }

    public function primary(): self
    {
        $this->attributes['primary'] = true;
        return $this;
    }

    public function unique(): self
    {
        $this->attributes['unique'] = true;
        return $this;
    }

    public function index(): self
    {
        $this->attributes['index'] = true;
        return $this;
    }

    public function comment(string $comment): self
    {
        $this->attributes['comment'] = $comment;
        return $this;
    }

    public function after(string $column): self
    {
        $this->attributes['after'] = $column;
        return $this;
    }

    public function first(): self
    {
        $this->attributes['first'] = true;
        return $this;
    }

    public function charset(string $charset): self
    {
        $this->attributes['charset'] = $charset;
        return $this;
    }

    public function collation(string $collation): self
    {
        $this->attributes['collation'] = $collation;
        return $this;
    }

    // Laravel-style constrained method
    public function constrained(?string $table = null, string $column = 'id'): ForeignKeyDefinition
    {
        if (!isset($this->blueprint)) {
            throw new \RuntimeException('Blueprint not set on column definition');
        }

        return $this->blueprint->constrained($table, $column);
    }

    // Additional foreign key methods for fluent API
    public function references(string $column): ForeignKeyDefinition
    {
        if (!isset($this->blueprint)) {
            throw new \RuntimeException('Blueprint not set on column definition');
        }

        return $this->blueprint->foreign($this->getName())->references($column);
    }

    // Validation methods
    public function isNullable(): bool
    {
        return $this->attributes['nullable'] ?? false;
    }

    public function hasDefault(): bool
    {
        return array_key_exists('default', $this->attributes);
    }

    public function isUnsigned(): bool
    {
        return $this->attributes['unsigned'] ?? false;
    }

    public function isAutoIncrement(): bool
    {
        return $this->attributes['autoIncrement'] ?? false;
    }

    public function isPrimary(): bool
    {
        return $this->attributes['primary'] ?? false;
    }

    public function isUnique(): bool
    {
        return $this->attributes['unique'] ?? false;
    }

    public function hasIndex(): bool
    {
        return $this->attributes['index'] ?? false;
    }

    // Type-specific modifiers
    public function length(int $length): self
    {
        $this->attributes['length'] = $length;
        return $this;
    }

    public function precision(int $precision): self
    {
        $this->attributes['precision'] = $precision;
        return $this;
    }

    public function scale(int $scale): self
    {
        $this->attributes['scale'] = $scale;
        return $this;
    }

    public function total(int $total): self
    {
        $this->attributes['total'] = $total;
        return $this;
    }

    public function places(int $places): self
    {
        $this->attributes['places'] = $places;
        return $this;
    }

    // String column modifiers
    public function binary(): self
    {
        $this->attributes['binary'] = true;
        return $this;
    }

    // Numeric column modifiers
    public function zerofill(): self
    {
        $this->attributes['zerofill'] = true;
        return $this;
    }

    // Enum/Set modifiers
    public function allowed(array $values): self
    {
        $this->attributes['allowed'] = $values;
        return $this;
    }

    // JSON column modifiers (for databases that support it)
    public function jsonExtract(string $path): self
    {
        $this->attributes['jsonExtract'] = $path;
        return $this;
    }

    // Timestamp modifiers
    public function useCurrent(): self
    {
        $this->attributes['useCurrent'] = true;
        return $this;
    }

    public function useCurrentOnUpdate(): self
    {
        $this->attributes['useCurrentOnUpdate'] = true;
        return $this;
    }

    // Generated column support
    public function storedAs(string $expression): self
    {
        $this->attributes['storedAs'] = $expression;
        return $this;
    }

    public function virtualAs(string $expression): self
    {
        $this->attributes['virtualAs'] = $expression;
        return $this;
    }

    // Validation helpers
    public function isGenerated(): bool
    {
        return isset($this->attributes['storedAs']) || isset($this->attributes['virtualAs']);
    }

    public function isStored(): bool
    {
        return isset($this->attributes['storedAs']);
    }

    public function isVirtual(): bool
    {
        return isset($this->attributes['virtualAs']);
    }

    // Magic method for setting attributes
    public function __call(string $method, array $arguments): self
    {
        // Allow setting custom attributes via method calls
        $this->attributes[$method] = $arguments[0] ?? true;
        return $this;
    }

    // Convert to array for serialization
    public function toArray(): array
    {
        return $this->attributes;
    }

    // String representation for debugging
    public function __toString(): string
    {
        return json_encode($this->attributes);
    }
}
