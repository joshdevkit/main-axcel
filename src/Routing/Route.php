<?php

namespace Axcel\Routing;

class Route
{
    private array $methods;
    private string $uri;
    private $action;
    private array $middlewares = [];
    private ?string $namespace = null;
    private array $parameters = [];
    private ?string $compiledUri = null;
    private array $parameterNames = [];
    private ?string $name = null; // Add name property

    public function __construct(array $methods, string $uri, $action)
    {
        $this->methods = array_map('strtoupper', $methods);
        $this->uri = $uri;
        $this->action = $action;
        $this->compileUri();
    }

    private function compileUri(): void
    {
        $uri = $this->uri;

        // Find all parameters in the URI (e.g., {id}, {user}, {slug})
        preg_match_all('/\{([^}]+)\}/', $uri, $matches);

        if (!empty($matches[1])) {
            $this->parameterNames = $matches[1];

            // Replace each parameter with a named capture group
            foreach ($matches[1] as $paramName) {
                // Check if parameter has constraints like {id:pattern}
                if (strpos($paramName, ':') !== false) {
                    [$paramName, $pattern] = explode(':', $paramName, 2);
                    $uri = str_replace('{' . $paramName . ':' . $pattern . '}', '(?P<' . $paramName . '>' . $pattern . ')', $uri);
                } else {
                    // Default to matching any non-slash characters
                    $uri = str_replace('{' . $paramName . '}', '(?P<' . $paramName . '>[^/]+)', $uri);
                }
            }
        }

        // Escape forward slashes and make the pattern exact
        $this->compiledUri = '#^' . str_replace('/', '\/', $uri) . '$#';
    }

    public function matchesUri(string $uri): bool
    {
        return preg_match($this->compiledUri, $uri);
    }


    public function matches(string $method, string $uri): bool
    {
        // Check if method matches
        if (!in_array($method, $this->methods)) {
            return false;
        }

        // Check if URI matches the compiled pattern
        return preg_match($this->compiledUri, $uri);
    }

    public function getParameters(string $uri): array
    {
        if (preg_match($this->compiledUri, $uri, $matches)) {
            $parameters = [];

            // Extract named parameters
            foreach ($this->parameterNames as $paramName) {
                if (isset($matches[$paramName])) {
                    $parameters[$paramName] = $matches[$paramName];
                }
            }

            return $parameters;
        }

        return [];
    }

    public function middleware(string|array $middlewares): self
    {
        if (is_array($middlewares)) {
            $this->middlewares = array_merge($this->middlewares, $middlewares);
        } else {
            $this->middlewares[] = $middlewares;
        }
        return $this;
    }

    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    public function namespace(string $namespace): self
    {
        $this->namespace = $namespace;
        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function where(array $patterns): self
    {
        foreach ($patterns as $param => $pattern) {
            $this->parameters['where'][$param] = $pattern;
        }
        return $this;
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getAction()
    {
        return $this->action;
    }


    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    public function getParameterNames(): array
    {
        return $this->parameterNames;
    }

    public function getCompiledUri(): string
    {
        return $this->compiledUri;
    }

    public function getName(): ?string
    {
        return $this->name;
    }
}
