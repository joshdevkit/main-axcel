<?php

namespace Axcel\View;

class ViewPathException extends \Exception
{
    protected string $attemptedPath;
    protected array $searchPaths;

    public function __construct(string $message, string $attemptedPath = '', array $searchPaths = [], int $code = 0, ?\Throwable $previous = null)
    {
        $this->attemptedPath = $attemptedPath;
        $this->searchPaths = $searchPaths;
        parent::__construct($message, $code, $previous);
    }

    public function getAttemptedPath(): string
    {
        return $this->attemptedPath;
    }

    public function getSearchPaths(): array
    {
        return $this->searchPaths;
    }
}
