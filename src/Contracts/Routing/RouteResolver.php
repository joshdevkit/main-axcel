<?php

namespace Axcel\Contracts\Routing;

use Axcel\Container\Container;
use Axcel\Http\Request;
use Axcel\Http\FormRequestValidationException;
use Axcel\Http\AuthorizationException;
use Closure;
use Exception;
use Axcel\Http\Response;
use Axcel\Routing\Route;
use Axcel\Routing\Router;
use ReflectionMethod;
use ReflectionFunction;

class RouteResolver
{
    private Container $container;
    private Router $router;

    public function __construct(Container $container, Router $router)
    {
        $this->container = $container;
        $this->router = $router;
    }

    public function dispatch(?Request $request = null): void
    {
        if ($request === null) {
            $request = $this->container->get('request');
        }

        $originalMethod = $request->getMethod();
        $uri = $request->getPathInfo();

        // First, try to find routes that match the URI pattern
        $matchingRoutes = [];
        foreach ($this->router->getRoutes() as $route) {
            if ($route->matchesUri($uri)) {
                $matchingRoutes[] = $route;
            }
        }

        if (empty($matchingRoutes)) {
            $this->handleNotFound();
            return;
        }

        // Handle method spoofing for POST requests
        $actualMethod = $this->getActualMethod($request, $originalMethod);
        // Try to find a route that matches both URI and method
        foreach ($matchingRoutes as $route) {
            if ($route->matches($actualMethod, $uri)) {
                $this->runRoute($route, $uri, $request);
                return;
            }
        }

        // If we get here, validate method spoofing and handle method not allowed
        $this->validateMethodSpoofingForMatchingRoutes($matchingRoutes, $request);
        $this->handleMethodNotAllowed($matchingRoutes);
    }

    private function getActualMethod(Request $request, string $originalMethod): string
    {
        if ($originalMethod !== 'POST') {
            return $originalMethod;
        }

        $methodOverride = null;
        if ($request->has('_method')) {
            $methodOverride = strtoupper($request->input('_method'));
        }

        if (!$methodOverride) {
            return $originalMethod;
        }

        $allowedMethods = ['PUT', 'PATCH', 'DELETE'];
        if (!in_array($methodOverride, $allowedMethods)) {
            throw new Exception("Invalid _method value: {$methodOverride}. Allowed methods are: " . implode(', ', $allowedMethods));
        }

        return $methodOverride;
    }

    private function validateMethodSpoofingForMatchingRoutes(array $matchingRoutes, Request $request): void
    {
        $requestMethod = $request->getMethod();

        if ($requestMethod !== 'POST' || $request->isJson()) {
            return;
        }

        $requiresSpoofing = false;
        $expectedMethods = [];

        foreach ($matchingRoutes as $route) {
            $routeMethods = $route->getMethods();
            $spoofingMethods = array_intersect(['DELETE', 'PUT', 'PATCH'], $routeMethods);

            if (!empty($spoofingMethods)) {
                $requiresSpoofing = true;
                $expectedMethods = array_merge($expectedMethods, $spoofingMethods);
            }
        }

        if ($requiresSpoofing && !$request->has('_method')) {
            $expectedMethods = array_unique($expectedMethods);
            $methodList = implode('/', $expectedMethods);
            throw new Exception(
                "Method spoofing required: Route expects {$methodList} but received POST without _method field. " .
                    "Missing <input type=\"hidden\" name=\"_method\" value=\"{$expectedMethods[0]}\"> ."
            );
        }
    }

    private function handleMethodNotAllowed(array $matchingRoutes): void
    {
        $allowedMethods = [];
        foreach ($matchingRoutes as $route) {
            $allowedMethods = array_merge($allowedMethods, $route->getMethods());
        }
        http_response_code(405);
        throw new Exception("Method not Allowed", 405);
    }

    private function runRoute(Route $route, string $uri, Request $request): void
    {
        $parameters = $route->getParameters($uri);
        $request->setRouteParameters($parameters);

        $middlewares = $route->getMiddlewares();
        $this->runMiddlewares($middlewares, $request, function () use ($route, $parameters, $request) {
            $this->executeRoute($route, $parameters, $request);
        });
    }

    private function runMiddlewares(array $middlewares, Request $request, Closure $next): void
    {
        if (empty($middlewares)) {
            $next();
            return;
        }

        $middleware = array_shift($middlewares);
        $middlewareClass = $this->resolveMiddleware($middleware);

        if ($middlewareClass) {
            $instance = $this->container->get($middlewareClass);

            // Auto-resolve auth middleware
            if ($this->isAuthMiddleware($middlewareClass)) {
                $this->resolveAuthMiddleware($instance, $request);
            }

            $instance->handle($request, function ($req) use ($middlewares, $next) {
                $this->runMiddlewares($middlewares, $req, $next);
            });
        } else {
            $this->runMiddlewares($middlewares, $request, $next);
        }
    }

    private function resolveMiddleware(string $middleware): ?string
    {
        $middlewares = $this->router->getMiddlewares();
        return $middlewares[$middleware] ?? (class_exists($middleware) ? $middleware : null);
    }

    private function isAuthMiddleware(string $middlewareClass): bool
    {
        $authMiddlewares = [
            'auth' => \Axcel\Http\Middleware\AuthMiddleware::class,
            'guest' => \Axcel\Http\Middleware\GuestMiddleware::class,
        ];

        return in_array($middlewareClass, $authMiddlewares) ||
            str_contains(strtolower($middlewareClass), 'auth');
    }

    private function resolveAuthMiddleware($middleware, Request $request): void
    {
        // Auto-inject auth resolver if the middleware has a setAuthResolver method
        if (method_exists($middleware, 'setAuthResolver')) {
            $authResolver = $this->container->get('auth.resolver');
            $middleware->setAuthResolver($authResolver);
        }
    }

    private function executeRoute(Route $route, array $parameters, Request $request): void
    {
        $action = $route->getAction();

        if ($action instanceof Closure) {
            $this->executeClosure($action, $parameters, $request);
        } elseif (is_string($action)) {
            $this->executeController($action, $parameters, $request, $route->getNamespace());
        } elseif (is_array($action)) {
            $this->executeControllerAction($action, $parameters, $request);
        }
    }

    private function executeClosure(Closure $closure, array $parameters, Request $request): void
    {
        $reflection = new ReflectionFunction($closure);
        $dependencies = $this->resolveDependencies($reflection->getParameters(), $parameters, $request);

        $result = $reflection->invokeArgs($dependencies);
        $this->handleResponse($result);
    }

    private function executeController(string $action, array $parameters, Request $request, ?string $namespace = null): void
    {
        if (strpos($action, '@') !== false) {
            [$controller, $method] = explode('@', $action, 2);
        } else {
            $controller = $action;
            $method = '__invoke';
        }

        if ($namespace) {
            $controller = $namespace . '\\' . $controller;
        }

        $this->executeControllerAction([$controller, $method], $parameters, $request);
    }


    private function executeControllerAction(array $action, array $parameters, Request $request): void
    {
        [$controller, $method] = $action;

        if (!class_exists($controller)) {
            throw new Exception("Controller class not found: $controller");
        }

        $instance = $this->container->get($controller);

        if (!method_exists($instance, $method)) {
            throw new Exception("Method $method not found in controller $controller");
        }

        $reflection = new ReflectionMethod($instance, $method);
        $dependencies = $this->resolveDependencies($reflection->getParameters(), $parameters, $request);

        $result = $reflection->invokeArgs($instance, $dependencies);
        $this->handleResponse($result);
    }

    private function resolveDependencies(array $parameters, array $routeParameters, $nativeRequest = null): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();
            $typeName = $this->getTypeName($type);

            // Handle FormRequest injection (extend this to check your base FormRequest)
            if ($typeName && is_subclass_of($typeName, \Axcel\Http\FormRequest::class)) {
                /** @var \Axcel\Http\FormRequest $formRequest */
                $formRequest = $typeName::createFrom($nativeRequest);
                $formRequest->validateResolved();
                $dependencies[] = $formRequest;
                continue;
            }

            // Handle model binding
            if ($typeName && class_exists($typeName)) {
                foreach ($routeParameters as $key => $value) {
                    if (is_scalar($value)) {
                        try {
                            $model = $this->resolveModelBinding($typeName, $value);
                            if ($model) {
                                $dependencies[] = $model;
                                continue 2;
                            }
                        } catch (\Exception $e) {
                            // silently fail model binding
                        }
                    }
                }
            }

            // Exact name match fallback
            if (array_key_exists($name, $routeParameters)) {
                $dependencies[] = $routeParameters[$name];
                continue;
            }

            // Container or default
            if ($typeName && class_exists($typeName)) {
                $dependencies[] = $this->container->get($typeName);
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }

            throw new \Exception("Cannot resolve parameter: $name");
        }

        return $dependencies;
    }


    private function resolveModelBinding(string $modelClass, $value)
    {
        if ($this->container->has($modelClass)) {
            $modelInstance = $this->container->get($modelClass);
        } else {
            $modelInstance = new $modelClass();
        }

        if (method_exists($modelInstance, 'find')) {
            $model = $modelInstance::find($value);
            if ($model) {
                return $model;
            }
            http_response_code(404);
            throw new Exception("Model [$modelClass] not found for ID [$value]");
        }

        if (method_exists($modelInstance, 'resolveRouteBinding')) {
            $model = $modelInstance->resolveRouteBinding($value);
            if ($model) {
                return $model;
            }
            http_response_code(404);
            throw new Exception("Model [$modelClass] not found for value [$value]");
        }

        throw new Exception("Model binding not supported for [$modelClass]. Model must implement 'find' method or 'resolveRouteBinding' method.");
    }

    private function getTypeName(?\ReflectionType $type): ?string
    {
        if (!$type) {
            return null;
        }

        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $unionType) {
                if ($unionType instanceof \ReflectionNamedType && !$unionType->isBuiltin()) {
                    return $unionType->getName();
                }
            }
            return null;
        }

        if ($type instanceof \ReflectionIntersectionType) {
            $types = $type->getTypes();
            if (!empty($types) && $types[0] instanceof \ReflectionNamedType) {
                return $types[0]->getName();
            }
            return null;
        }

        if ($type instanceof \ReflectionNamedType) {
            return $type->isBuiltin() ? null : $type->getName();
        }

        return null;
    }

    public function handleResponse($result): void
    {
        if ($result instanceof Response) {
            $result->send();
            return;
        }

        if ($result === null) {
            return;
        }

        if (is_string($result)) {
            echo $result;
            return;
        }

        if (is_array($result) || is_object($result)) {
            header('Content-Type: application/json');
            echo json_encode($result);
            return;
        }

        echo (string) $result;
    }

    private function handleNotFound(): void
    {
        http_response_code(404);
        app()->renderNotFound();
    }

    public function url(string $name, array $params = []): string
    {
        $route = $this->findRouteByName($name);

        if (!$route) {
            throw new \InvalidArgumentException("Route named '{$name}' not found.");
        }

        $uri = $route->getUri();
        $requiredParams = $route->getParameterNames(); // ['user', 'post', etc.]

        // Check for missing parameters
        foreach ($requiredParams as $param) {
            if (!array_key_exists($param, $params)) {
                throw new \InvalidArgumentException("Missing route parameter '{$param}' for route '{$name}'.");
            }

            $uri = str_replace('{' . $param . '}', $params[$param], $uri);
        }

        // Check for extra parameters not used in the URI
        $extraParams = array_diff_key($params, array_flip($requiredParams));
        if (!empty($extraParams)) {
            $extraKeys = implode(', ', array_keys($extraParams));
            throw new \InvalidArgumentException("Unexpected parameter(s) for route '{$name}': {$extraKeys}.");
        }

        return '/' . ltrim($uri, '/');
    }


    private function findRouteByName(string $name): ?Route
    {
        foreach ($this->router->getRoutes() as $route) {
            if ($route->getName() === $name) {
                return $route;
            }
        }
        return null;
    }


    public function action(string $controllerAction, array $params = []): string
    {
        foreach ($this->router->getRoutes() as $route) {
            $action = $route->getAction();

            if (is_array($action)) {
                [$controller, $method] = $action;
                if ("{$controller}@{$method}" === $controllerAction) {
                    return $this->buildUrlFromRoute($route, $params);
                }
            }

            if (is_string($action) && $action === $controllerAction) {
                return $this->buildUrlFromRoute($route, $params);
            }
        }

        throw new Exception("Action [{$controllerAction}] not defined.");
    }

    private function buildUrlFromRoute(Route $route, array $params): string
    {
        $uri = $route->getUri();

        foreach ($params as $key => $value) {
            $uri = preg_replace('/\{' . preg_quote($key) . '\??\}/', $value, $uri);
        }

        $uri = preg_replace('/\{[^}]+\?\}/', '', $uri);
        $uri = preg_replace('#/+#', '/', $uri);
        $uri = rtrim($uri, '/');

        if (empty($uri)) {
            $uri = '/';
        }

        return $uri;
    }
}
