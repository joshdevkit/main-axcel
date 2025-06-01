<?php

namespace Axcel\Support;

class StrObject
{
    protected array|string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function explode(string $delimiter): self
    {
        $this->value = explode($delimiter, $this->value);
        return $this;
    }

    public function map(callable $callback): self
    {
        $this->value = array_map($callback, $this->value);
        return $this;
    }

    public function substr(int $start, ?int $length = null): self
    {
        if (is_array($this->value)) {
            $this->value = array_map(fn($v) => substr($v, $start, $length), $this->value);
        } else {
            $this->value = substr($this->value, $start, $length);
        }
        return $this;
    }

    public function implode(string $glue): string
    {
        return is_array($this->value) ? implode($glue, $this->value) : (string) $this->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
