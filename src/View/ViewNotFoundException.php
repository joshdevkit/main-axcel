<?php

namespace Axcel\View;

// Exception classes remain the same
class ViewNotFoundException extends \Exception
{
    protected string $viewName;
    protected string $searchPath;

    public function __construct(string $message, string $viewName = '', string $searchPath = '', int $code = 0, ?\Throwable $previous = null)
    {
        $this->viewName = $viewName;
        $this->searchPath = $searchPath;
        parent::__construct($message, $code, $previous);
    }

    public function getViewName(): string
    {
        return $this->viewName;
    }

    public function getSearchPath(): string
    {
        return $this->searchPath;
    }
}
