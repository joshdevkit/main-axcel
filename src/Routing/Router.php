<?php

namespace Axcel\Routing;

use Axcel\Container\Container;
use Axcel\Http\Request;
use Closure;
use Axcel\Contracts\Routing\RouteRegistrar;
use Axcel\Contracts\Routing\RouteResolver;

class Router
{
    private Container $container;
    private RouteRegistrar $registrar;
    private RouteResolver $resolver;
    private array $middlewares = [];
    private array $patterns = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->registrar = new RouteRegistrar($this);
        $this->resolver = new RouteResolver($container, $this);
        $this->registerDefaultPatterns();
        $this->registerDefaultMiddlewares();
    }

    private function registerDefaultPatterns(): void
    {
        $this->patterns = [
            'id' => '[0-9]+',
            'slug' => '[a-z0-9-]+',
            'uuid' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
            'any' => '.*'
        ];
    }

    private function registerDefaultMiddlewares(): void
    {
        // Register common middlewares with their class mappings
        $this->middlewares = [
            'auth' => \Axcel\Http\Middleware\AuthMiddleware::class,
            'guest' => \Axcel\Http\Middleware\GuestMiddleware::class,
            // Add more default middlewares as needed
        ];
    }

    // Route registration methods - delegate to registrar
    public function get(string $uri, $action): Route
    {
        return $this->registrar->get($uri, $action);
    }

    public function post(string $uri, $action): Route
    {
        return $this->registrar->post($uri, $action);
    }

    public function put(string $uri, $action): Route
    {
        return $this->registrar->put($uri, $action);
    }

    public function patch(string $uri, $action): Route
    {
        return $this->registrar->patch($uri, $action);
    }

    public function delete(string $uri, $action): Route
    {
        return $this->registrar->delete($uri, $action);
    }

    public function options(string $uri, $action): Route
    {
        return $this->registrar->options($uri, $action);
    }

    public function any(string $uri, $action): Route
    {
        return $this->registrar->any($uri, $action);
    }

    public function match(array $methods, string $uri, $action): Route
    {
        return $this->registrar->match($methods, $uri, $action);
    }

    public function resource(string $name, string $controller, array $options = []): void
    {
        $this->registrar->resource($name, $controller, $options);
    }

    public function controller(string $controller): RouteRegistrar
    {
        return $this->registrar->controller($controller);
    }

    public function group($attributesOrCallback, ?Closure $callback = null): void
    {
        $this->registrar->group($attributesOrCallback, $callback);
    }

    /**
     * Apply middleware to routes (for chaining)
     * This method should return a RouteRegistrar for chaining
     * 
     * @param string|array $middleware Middleware name(s) to apply
     * @return RouteRegistrar
     */
    public function middleware($middleware): RouteRegistrar
    {
        return $this->registrar->middleware($middleware);
    }

    /**
     * Register a middleware alias with its class
     * 
     * @param string $name Middleware alias
     * @param string $class Middleware class name
     * @return void
     */
    public function registerMiddleware(string $name, string $class): void
    {
        $this->middlewares[$name] = $class;
    }

    /**
     * Register multiple middlewares at once
     * 
     * @param array $middlewares Array of name => class pairs
     * @return void
     */
    public function registerMiddlewares(array $middlewares): void
    {
        $this->middlewares = array_merge($this->middlewares, $middlewares);
    }

    /**
     * Resolve middleware name to class name
     * 
     * @param string $middleware
     * @return string
     */
    public function resolveMiddleware(string $middleware): string
    {
        return $this->middlewares[$middleware] ?? $middleware;
    }

    /**
     * Check if middleware is registered
     * 
     * @param string $middleware
     * @return bool
     */
    public function hasMiddleware(string $middleware): bool
    {
        return isset($this->middlewares[$middleware]);
    }

    public function pattern(string $key, string $pattern): void
    {
        $this->patterns[$key] = $pattern;
    }

    // Route resolution methods - delegate to resolver
    public function dispatch(?Request $request = null): void
    {
        $this->resolver->dispatch($request);
    }

    public function url(string $name, array $params = []): string
    {
        return $this->resolver->url($name, $params);
    }

    public function action(string $controllerAction, array $params = []): string
    {
        return $this->resolver->action($controllerAction, $params);
    }

    public function route(string $name, array $params = []): string
    {
        return $this->url($name, $params);
    }

    // Getters for registrar and resolver
    public function getRoutes(): array
    {
        return $this->registrar->getRoutes();
    }

    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    public function getPatterns(): array
    {
        return $this->patterns;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }
}
