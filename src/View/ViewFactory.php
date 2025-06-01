<?php

namespace Axcel\View;

use Axcel\Facades\FacadesResolver;

class ViewFactory
{
    protected string $viewsPath;
    protected array $sections = [];
    protected array $sectionStack = [];
    protected ?string $extendedView = null;
    protected bool $isExtending = false;

    public function __construct(string $viewsPath)
    {
        $this->viewsPath = rtrim($viewsPath, '/');
        FacadesResolver::register();
    }

    public function make(string $view, array $data = []): string
    {
        // Reset state for each new view render
        $this->sections = [];
        $this->sectionStack = [];
        $this->extendedView = null;
        $this->isExtending = false;

        $file = $this->resolveViewPath($view);

        if (!file_exists($file)) {
            throw new ViewNotFoundException(
                "View [$view] not found at path: $file",
                $view,
                $file
            );
        }

        return $this->evaluateView($file, $data);
    }

    protected function resolveViewPath(string $view): string
    {
        return $this->viewsPath . '/' . str_replace('.', '/', $view) . '.php';
    }

    protected function evaluateView(string $path, array $data): string
    {
        $obLevel = ob_get_level();
        ob_start();

        $__path = $path;
        $__data = $data;

        extract($__data, EXTR_SKIP);
        $view = $this;

        include $__path;

        $content = ob_get_clean();

        // If this view extends another, render the parent with sections
        if ($this->extendedView) {
            return $this->renderExtendedView($this->extendedView, $data);
        }

        return ltrim($content);
    }

    protected function renderExtendedView(string $parentView, array $data): string
    {
        $file = $this->resolveViewPath($parentView);

        if (!file_exists($file)) {
            throw new ViewNotFoundException(
                "Extended view [$parentView] not found at path: $file",
                $parentView,
                $file
            );
        }

        $obLevel = ob_get_level();
        ob_start();

        $__path = $file;
        $__data = $data;

        extract($__data, EXTR_SKIP);
        $view = $this;

        include $__path;

        return ltrim(ob_get_clean());
    }

    // NEW: Include method that doesn't reset sections
    protected function evaluateInclude(string $path, array $data): string
    {
        $obLevel = ob_get_level();
        ob_start();

        $__path = $path;
        $__data = $data;

        extract($__data, EXTR_SKIP);
        $view = $this;

        include $__path;

        return ltrim(ob_get_clean());
    }

    protected function handleViewException(\Throwable $e, int $obLevel): void
    {
        while (ob_get_level() > $obLevel) {
            ob_end_clean();
        }

        $path = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['args'][0] ?? '';
        $data = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['args'][1] ?? [];

        throw new ViewRenderingException(
            'Error rendering view: ' . $e->getMessage(),
            $path,
            $data,
            0,
            $e
        );
    }

    // Template inheritance methods
    public function extends(string $view): void
    {
        $this->extendedView = $view;
        $this->isExtending = true;
    }

    public function yield(string $name, string $default = ''): void
    {
        $content = $this->getSection($name, $default);
        // Add proper formatting by preserving whitespace and adding newlines where appropriate
        if (!empty($content)) {
            echo "\n" . trim($content) . "\n";
        }
    }

    // Section methods (enhanced)
    public function section(string $name, ?string $content = null): void
    {
        if ($content !== null) {
            // Inline section definition
            $this->sections[$name] = $content;
        } else {
            // Start capturing section content
            $this->startSection($name);
        }
    }

    public function startSection(string $name): void
    {
        if (empty($name)) {
            throw new InvalidSectionException('Section name cannot be empty');
        }

        $this->sectionStack[] = $name;
        ob_start();
    }

    public function endSection(): void
    {
        if (empty($this->sectionStack)) {
            throw new InvalidSectionException('No section started - cannot end section');
        }

        $name = array_pop($this->sectionStack);
        $content = ob_get_clean();
        // Preserve the original formatting by trimming only leading/trailing whitespace
        $this->sections[$name] = $content;
    }

    public function getSection(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]);
    }

    // FIXED: Include method that preserves sections
    public function include(string $view, array $data = []): void
    {
        $file = $this->resolveViewPath($view);

        if (!file_exists($file)) {
            throw new ViewNotFoundException(
                "Included view [$view] not found at path: $file",
                $view,
                $file
            );
        }

        // Use evaluateInclude instead of make to preserve sections
        echo $this->evaluateInclude($file, $data);
    }

    // Escape helper for templates
    public function escape(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    // Additional helper methods
    public function raw(string $content): string
    {
        return $content;
    }

    public function show(string $section, string $default = ''): void
    {
        echo $this->getSection($section, $default);
    }

    // Stack support for sections (append content)
    public function push(string $name, string $content): void
    {
        if (!isset($this->sections[$name])) {
            $this->sections[$name] = '';
        }
        $this->sections[$name] .= $content;
    }

    public function prepend(string $name, string $content): void
    {
        if (!isset($this->sections[$name])) {
            $this->sections[$name] = '';
        }
        $this->sections[$name] = $content . $this->sections[$name];
    }
}
