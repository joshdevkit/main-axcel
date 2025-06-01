<?php

namespace Axcel\Database\Schema\Definitions;

class IndexDefinition
{
    protected array $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    /**
     * Get an attribute value
     */
    public function get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Set an attribute value
     */
    public function set(string $key, mixed $value): self
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * Get all attributes
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get the index name
     */
    public function getName(): string
    {
        return $this->attributes['name'] ?? '';
    }

    /**
     * Get the index columns
     */
    public function getColumns(): array
    {
        return $this->attributes['columns'] ?? [];
    }

    /**
     * Get the index type
     */
    public function getType(): string
    {
        return $this->attributes['type'] ?? 'index';
    }
}
