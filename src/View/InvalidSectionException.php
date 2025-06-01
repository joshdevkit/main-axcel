<?php

namespace Axcel\View;


class InvalidSectionException extends \Exception
{
    protected array $activeSections;

    public function __construct(string $message, array $activeSections = [], int $code = 0, ?\Throwable $previous = null)
    {
        $this->activeSections = $activeSections;
        parent::__construct($message, $code, $previous);
    }

    public function getActiveSections(): array
    {
        return $this->activeSections;
    }
}
