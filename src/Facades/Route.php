<?php

namespace Axcel\Facades;

use Axcel\Support\Facade;
use Closure;

/**
 * Route Facade
 * 
 * @method static RouteInstance get(string $uri, mixed $action)
 * @method static RouteInstance post(string $uri, mixed $action)
 * @method static RouteInstance put(string $uri, mixed $action)
 * @method static RouteInstance patch(string $uri, mixed $action)
 * @method static RouteInstance delete(string $uri, mixed $action)
 * @method static RouteInstance options(string $uri, mixed $action)
 * @method static RouteInstance any(string $uri, mixed $action)
 * @method static RouteInstance match(array $methods, string $uri, mixed $action)
 * @method static \Axcel\Routing\Router middleware(array|string $middleware)
 * @method static RouteInstance prefix(string $prefix)
 * @method static self controller(string $controller)
 * @method static void group($attributesOrCallback, Closure $callback)
 * @method static void pattern(string $key, string $pattern)
 * @method static void dispatch(\Axcel\Http\Request|null $request = null)
 * @method static string url(string $name, array $params = [])
 * @method static string action(string $controllerAction, array $params = [])
 * @method static string route(string $name, array $params = [])
 * @method static RouteInstance resource(string $name, string $controller, array $options = [])
 * @method static RouteInstance prefix()
 * 
 * @see \Axcel\Routing\Router
 */
class Route extends Facade
{
    /**
     * Get the registered component name.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'route';
    }
}
