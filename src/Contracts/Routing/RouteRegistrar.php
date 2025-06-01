<?php

namespace Axcel\Contracts\Routing;

use Closure;
use Axcel\Routing\Route;
use Axcel\Routing\Router;

class RouteRegistrar
{
    private Router $router;
    private array $routes = [];
    private array $groupStack = [];
    private string $currentGroupPrefix = '';
    private array $currentGroupMiddlewares = [];
    private ?string $currentGroupNamespace = null;
    private ?string $currentGroupController = null;

    // Add these properties for temporary middleware/controller chaining
    private array $pendingMiddlewares = [];
    private ?string $pendingController = null;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * Apply middleware for chaining (Route::middleware('auth')->group(...))
     * 
     * @param string|array $middleware
     * @return self
     */
    public function middleware($middleware): self
    {
        $middlewares = is_array($middleware) ? $middleware : [$middleware];
        $this->pendingMiddlewares = array_merge($this->pendingMiddlewares, $middlewares);
        return $this;
    }

    public function get(string $uri, $action): Route
    {
        return $this->addRoute(['GET'], $uri, $action);
    }

    public function post(string $uri, $action): Route
    {
        return $this->addRoute(['POST'], $uri, $action);
    }

    public function put(string $uri, $action): Route
    {
        return $this->addRoute(['PUT'], $uri, $action);
    }

    public function patch(string $uri, $action): Route
    {
        return $this->addRoute(['PATCH'], $uri, $action);
    }

    public function delete(string $uri, $action): Route
    {
        return $this->addRoute(['DELETE'], $uri, $action);
    }

    public function options(string $uri, $action): Route
    {
        return $this->addRoute(['OPTIONS'], $uri, $action);
    }

    public function any(string $uri, $action): Route
    {
        return $this->addRoute(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $uri, $action);
    }

    public function match(array $methods, string $uri, $action): Route
    {
        return $this->addRoute($methods, $uri, $action);
    }

    public function resource(string $name, string $controller, array $options = []): void
    {
        $only = $options['only'] ?? null;
        $except = $options['except'] ?? null;
        $names = $options['names'] ?? [];
        $parameters = $options['parameters'] ?? [];

        // Auto-detect singular form from resource param name (e.g., flights → flight)
        $resourceParams = rtrim($name, 's');

        $defaultActions = [
            'index' => ['GET', ''],
            'create' => ['GET', '/create'],
            'store' => ['POST', ''],
            'show' => ['GET', '/{' . $resourceParams . '}'],
            'edit' => ['GET', '/{' . $resourceParams . '}/edit'],
            'update' => ['PUT', '/{' . $resourceParams . '}'],
            'destroy' => ['DELETE', '/{' . $resourceParams . '}']
        ];

        // Apply `only` or `except` filtering
        if ($only) {
            $defaultActions = array_intersect_key($defaultActions, array_flip($only));
        }

        if ($except) {
            $defaultActions = array_diff_key($defaultActions, array_flip($except));
        }

        foreach ($defaultActions as $action => $config) {
            [$method, $path] = $config;
            $uri = $name . $path;

            // Replace param names if user provided `parameters` map
            foreach ($parameters as $old => $new) {
                $uri = str_replace('{' . $old . '}', '{' . $new . '}', $uri);
            }

            $prefix = trim($this->currentGroupPrefix, '/');
            $fullName = ($prefix ? $prefix . '.' : '') . $name;
            $routeName = $names[$action] ?? $fullName . '.' . $action;
            $controllerMethod = $controller . '@' . $action;

            $route = $this->addRoute([$method], $uri, $controllerMethod);
            $route->name($routeName);
        }

        // Clear pending attributes after resource creation
        $this->clearPendingAttributes();
    }

    public function addRoute(array $methods, string $uri, $action): Route
    {
        $uri = $this->currentGroupPrefix . '/' . ltrim($uri, '/');
        $uri = '/' . trim($uri, '/');

        if ($uri === '/') {
            $uri = '/';
        }

        // Handle controller precedence: pending > current group > parameter
        $controllerToUse = $this->pendingController ?? $this->currentGroupController;

        // If controller is set and action is string (method name)
        if ($controllerToUse !== null && is_string($action)) {
            $action = [$controllerToUse, $action];
        }

        $route = new Route($methods, $uri, $action);

        // Apply all middlewares: group + pending
        $allMiddlewares = array_merge($this->currentGroupMiddlewares, $this->pendingMiddlewares);
        if (!empty($allMiddlewares)) {
            $route->middleware($allMiddlewares);
        }

        // Apply group namespace
        if ($this->currentGroupNamespace) {
            $route->namespace($this->currentGroupNamespace);
        }

        $this->routes[] = $route;

        // Clear pending attributes after creating individual route
        $this->clearPendingAttributes();

        return $route;
    }

    public function controller(string $controller): self
    {
        $this->pendingController = $controller;
        return $this;
    }

    public function group($attributesOrCallback, ?Closure $callback = null): void
    {
        // Handle controller()->group(closure) syntax
        if ($attributesOrCallback instanceof Closure && $callback === null) {
            $attributes = [];
            if ($this->pendingController !== null) {
                $attributes['controller'] = $this->pendingController;
            }
            if (!empty($this->pendingMiddlewares)) {
                $attributes['middleware'] = $this->pendingMiddlewares;
            }
            $callback = $attributesOrCallback;
        } else {
            $attributes = $attributesOrCallback;

            // Merge pending attributes into group attributes
            if ($this->pendingController !== null) {
                $attributes['controller'] = $this->pendingController;
            }
            if (!empty($this->pendingMiddlewares)) {
                $attributes['middleware'] = array_merge(
                    (array)($attributes['middleware'] ?? []),
                    $this->pendingMiddlewares
                );
            }
        }

        $this->updateGroupStack($attributes);
        $callback($this->router);
        array_pop($this->groupStack);
        $this->updateCurrentGroup();

        // Clear all pending and controller attributes after group
        $this->clearPendingAttributes();
        $this->currentGroupController = null;
    }

    private function updateGroupStack(array $attributes): void
    {
        $this->groupStack[] = $attributes;
        $this->updateCurrentGroup();
    }

    private function updateCurrentGroup(): void
    {
        $group = end($this->groupStack);

        if ($group === false) {
            $this->currentGroupPrefix = '';
            $this->currentGroupMiddlewares = [];
            $this->currentGroupNamespace = null;
            return;
        }

        // Build prefix - Fixed to handle empty prefixes correctly
        $prefixes = [];
        foreach ($this->groupStack as $g) {
            if (isset($g['prefix']) && !empty(trim($g['prefix'], '/'))) {
                $prefixes[] = trim($g['prefix'], '/');
            }
        }
        // Only add leading slash if we have prefixes
        $this->currentGroupPrefix = !empty($prefixes) ? '/' . implode('/', $prefixes) : '';

        // Build middleware stack
        $middlewares = [];
        foreach ($this->groupStack as $g) {
            if (isset($g['middleware'])) {
                $middlewares = array_merge($middlewares, (array) $g['middleware']);
            }
        }
        $this->currentGroupMiddlewares = $middlewares;

        // Get namespace
        $this->currentGroupNamespace = null;
        foreach ($this->groupStack as $g) {
            if (isset($g['namespace'])) {
                $this->currentGroupNamespace = $g['namespace'];
            }
        }

        // Handle controller from group attributes
        if (isset($group['controller'])) {
            $this->currentGroupController = $group['controller'];
        }
    }

    /**
     * Clear pending attributes (middleware and controller) after they've been applied
     */
    private function clearPendingAttributes(): void
    {
        $this->pendingMiddlewares = [];
        $this->pendingController = null;
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }
}
