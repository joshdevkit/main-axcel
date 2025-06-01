<?php

namespace Axcel\View;



class ViewRenderingException extends \Exception
{
    protected string $viewPath;
    protected array $viewData;

    public function __construct(string $message, string $viewPath = '', array $viewData = [], int $code = 0, ?\Throwable $previous = null)
    {
        $this->viewPath = $viewPath;
        $this->viewData = $viewData;
        parent::__construct($message, $code, $previous);
    }

    public function getViewPath(): string
    {
        return $this->viewPath;
    }

    public function getViewData(): array
    {
        return $this->viewData;
    }

    public function getDebugInfo(): array
    {
        return [
            'view_path' => $this->viewPath,
            'view_data_keys' => array_keys($this->viewData),
            'original_error' => $this->getPrevious() ? $this->getPrevious()->getMessage() : null,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }
}
