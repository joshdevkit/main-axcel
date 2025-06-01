<?php

namespace Axcel\Container;

use ReflectionClass;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionParameter;
use ReflectionType;
use ReflectionNamedType;
use ReflectionUnionType;
use ReflectionIntersectionType;
use Closure;
use Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class ContainerException extends Exception implements ContainerExceptionInterface {}
class NotFoundException extends Exception implements NotFoundExceptionInterface {}

class Container
{
    private static ?Container $instance = null;
    private array $bindings = [];
    private array $instances = [];
    private array $aliases = [];

    public function __construct() {}

    public static function getInstance(): Container
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function bind(string $abstract, $concrete = null, bool $shared = false): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared
        ];
    }

    public function singleton(string $abstract, $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    public function instance(string $abstract, $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $abstract;
    }

    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) ||
            isset($this->instances[$abstract]) ||
            isset($this->aliases[$abstract]) ||
            class_exists($abstract);
    }

    public function get(string $abstract)
    {
        // Check for alias
        if (isset($this->aliases[$abstract])) {
            $abstract = $this->aliases[$abstract];
        }

        // Check for existing instance
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Check for binding
        if (isset($this->bindings[$abstract])) {
            $binding = $this->bindings[$abstract];
            $concrete = $binding['concrete'];
            $shared = $binding['shared'];

            $instance = $this->build($concrete);

            if ($shared) {
                $this->instances[$abstract] = $instance;
            }

            return $instance;
        }

        // Try to auto-resolve
        if (class_exists($abstract)) {
            $instance = $this->build($abstract);
            return $instance;
        }

        throw new ContainerException("Cannot resolve '$abstract'");
    }

    public function call($callback, array $parameters = [])
    {
        if ($callback instanceof Closure) {
            $reflection = new ReflectionFunction($callback);
            $dependencies = $this->resolveDependencies($reflection->getParameters(), $parameters);
            return $reflection->invokeArgs($dependencies);
        }

        if (is_array($callback)) {
            [$class, $method] = $callback;

            if (is_string($class)) {
                $class = $this->get($class);
            }

            $reflection = new ReflectionMethod($class, $method);
            $dependencies = $this->resolveDependencies($reflection->getParameters(), $parameters);
            return $reflection->invokeArgs($class, $dependencies);
        }

        if (is_string($callback)) {
            if (strpos($callback, '@') !== false) {
                [$class, $method] = explode('@', $callback, 2);
                return $this->call([$class, $method], $parameters);
            }

            if (function_exists($callback)) {
                $reflection = new ReflectionFunction($callback);
                $dependencies = $this->resolveDependencies($reflection->getParameters(), $parameters);
                return $reflection->invokeArgs($dependencies);
            }
        }

        throw new ContainerException("Invalid callback provided");
    }

    private function build($concrete)
    {
        if ($concrete instanceof Closure) {
            return $concrete($this);
        }

        if (is_string($concrete)) {
            if (!class_exists($concrete)) {
                throw new ContainerException("Class '$concrete' does not exist");
            }

            $reflection = new ReflectionClass($concrete);

            if (!$reflection->isInstantiable()) {
                throw new ContainerException("Class '$concrete' is not instantiable");
            }

            $constructor = $reflection->getConstructor();

            if ($constructor === null) {
                return new $concrete();
            }

            $dependencies = $this->resolveDependencies($constructor->getParameters());

            return $reflection->newInstanceArgs($dependencies);
        }

        return $concrete;
    }

    private function resolveDependencies(array $parameters, array $provided = []): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            // Check if parameter is provided
            if (array_key_exists($name, $provided)) {
                $dependencies[] = $provided[$name];
                continue;
            }

            $dependencies[] = $this->resolveParameter($parameter);
        }

        return $dependencies;
    }

    private function resolveParameter(ReflectionParameter $parameter)
    {
        $type = $parameter->getType();

        if ($type === null) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }
            throw new ContainerException("Cannot resolve parameter '{$parameter->getName()}'");
        }

        $typeName = $this->getTypeName($type);

        // If we couldn't extract a valid type name or it's a built-in type
        if (!$typeName) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }
            throw new ContainerException("Cannot resolve parameter '{$parameter->getName()}' - no valid type found");
        }

        // Check if it's a built-in type
        if ($this->isBuiltinType($type, $typeName)) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }
            throw new ContainerException("Cannot resolve built-in type '$typeName' for parameter '{$parameter->getName()}'");
        }

        // Try to resolve from container
        try {
            return $this->get($typeName);
        } catch (ContainerException $e) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }
            throw new ContainerException("Cannot resolve parameter '{$parameter->getName()}': " . $e->getMessage());
        }
    }

    /**
     * Get the type name from ReflectionType, handling union types
     */
    private function getTypeName(?ReflectionType $type): ?string
    {
        if (!$type) {
            return null;
        }

        // Handle union types (PHP 8+)
        if ($type instanceof ReflectionUnionType) {
            // For union types, try to find the first non-builtin type
            foreach ($type->getTypes() as $unionType) {
                if ($unionType instanceof ReflectionNamedType && !$unionType->isBuiltin()) {
                    return $unionType->getName();
                }
            }
            return null;
        }

        // Handle intersection types (PHP 8.1+)
        if ($type instanceof ReflectionIntersectionType) {
            // For intersection types, get the first type (this is a simplified approach)
            $types = $type->getTypes();
            if (!empty($types) && $types[0] instanceof ReflectionNamedType) {
                return $types[0]->getName();
            }
            return null;
        }

        // Handle named types (most common case)
        if ($type instanceof ReflectionNamedType) {
            return $type->getName();
        }

        return null;
    }

    /**
     * Check if the type is a built-in type
     */
    private function isBuiltinType(?ReflectionType $type, ?string $typeName): bool
    {
        if (!$type || !$typeName) {
            return false;
        }

        // Handle union types
        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $unionType) {
                if ($unionType instanceof ReflectionNamedType && $unionType->getName() === $typeName) {
                    return $unionType->isBuiltin();
                }
            }
            return false;
        }

        // Handle intersection types
        if ($type instanceof ReflectionIntersectionType) {
            $types = $type->getTypes();
            if (!empty($types) && $types[0] instanceof ReflectionNamedType && $types[0]->getName() === $typeName) {
                return $types[0]->isBuiltin();
            }
            return false;
        }

        // Handle named types
        if ($type instanceof ReflectionNamedType && $type->getName() === $typeName) {
            return $type->isBuiltin();
        }

        return false;
    }

    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->aliases = [];
    }

    // Prevent cloning and unserialization
    private function __clone() {}
    public function __wakeup() {}
}
