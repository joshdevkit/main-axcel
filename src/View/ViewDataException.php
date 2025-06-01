<?php

namespace Axcel\View;

class ViewDataException extends \Exception
{
    protected array $invalidKeys;
    protected array $requiredKeys;

    public function __construct(string $message, array $invalidKeys = [], array $requiredKeys = [], int $code = 0, ?\Throwable $previous = null)
    {
        $this->invalidKeys = $invalidKeys;
        $this->requiredKeys = $requiredKeys;
        parent::__construct($message, $code, $previous);
    }

    public function getInvalidKeys(): array
    {
        return $this->invalidKeys;
    }

    public function getRequiredKeys(): array
    {
        return $this->requiredKeys;
    }
}
