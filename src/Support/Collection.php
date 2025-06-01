<?php

namespace Axcel\Support;

class Collection implements \ArrayAccess, \IteratorAggregate, \Countable,  \JsonSerializable
{
    protected array $items = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function all(): array
    {
        return $this->items;
    }

    public function map(callable $callback): static
    {
        return new static(array_map($callback, $this->items));
    }

    public function filter(?callable $callback = null): static
    {
        return new static(array_filter($this->items, $callback));
    }

    public function each(callable $callback): static
    {
        foreach ($this->items as $key => $item) {
            $callback($item, $key);
        }
        return $this;
    }

    public function pluck(string $key): static
    {
        return new static(array_map(fn($item) => is_array($item) ? ($item[$key] ?? null) : ($item->{$key} ?? null), $this->items));
    }

    public function first()
    {
        return reset($this->items);
    }

    public function toArray(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function offsetExists($offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset($offset): void
    {
        unset($this->items[$offset]);
    }

    // IteratorAggregate
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }

    public function jsonSerialize(): array
    {
        return array_map(function ($item) {
            return $item instanceof \JsonSerializable ? $item->jsonSerialize() : $item;
        }, $this->items);
    }

    public function implode(string $separator): string
    {
        return implode($separator, array_map(function ($item) {
            return (string) $item;
        }, $this->items));
    }


    // Countable
    public function count(): int
    {
        return count($this->items);
    }

    public static function make(array $items = []): static
    {
        return new static($items);
    }


    public function contains(string $key, mixed $value): bool
    {
        foreach ($this->items as $item) {
            if (is_array($item)) {
                if (($item[$key] ?? null) === $value) {
                    return true;
                }
            } elseif (is_object($item)) {
                if (($item->{$key} ?? null) === $value) {
                    return true;
                }
            }
        }

        return false;
    }
}
