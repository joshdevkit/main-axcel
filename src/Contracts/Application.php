<?php

namespace Axcel\Contracts;

use Axcel\Http\Request;
use Axcel\Routing\Router;

interface Application
{
    /**
     * Get the singleton instance of the application.
     */
    public static function getInstance(string $basePath = ''): self;

    /**
     * Bind an abstract type to a concrete implementation.
     */
    public function bind(string $abstract, $concrete = null, bool $shared = false): self;

    /**
     * Register a singleton binding.
     */
    public function singleton(string $abstract, $concrete = null): self;

    /**
     * Register an existing instance as shared.
     */
    public function instance(string $abstract, $instance): self;

    /**
     * Register an alias for an abstract type.
     */
    public function alias(string $abstract, string $alias): self;

    /**
     * Resolve a service from the container.
     */
    public function get(string $abstract);

    /**
     * Check if a service is bound in the container.
     */
    public function has(string $abstract): bool;

    /**
     * Call a callback with dependency injection.
     */
    public function call($callback, array $parameters = []);

    /**
     * Get the router instance.
     */
    public function router(): Router;

    /**
     * Get the request instance.
     */
    public function request(): Request;

    /**
     * Boot the application.
     */
    public function boot(): self;

    /**
     * Check if the application has been booted.
     */
    public function isBooted(): bool;

    /**
     * Run the application.
     */
    public function run(): void;

    /**
     * Add middleware to the application.
     */
    public function middleware(string $middleware): self;

    /**
     * Get all registered middlewares.
     */
    public function getMiddlewares(): array;

    /**
     * Get the base path of the application.
     */
    public function basePath(string $path = ''): string;

    /**
     * Get the app path.
     */
    public function appPath(string $path = ''): string;

    /**
     * Get the public path.
     */
    public function publicPath(string $path = ''): string;

    /**
     * Load routes from a file.
     */
    public function loadRoutes(string $path = ''): self;

    /**
     * Get the debug configuration.
     */
    public function appDebug(): string;

    /**
     * Get the current environment.
     */
    public function environment(): string;

    /**
     * Check if the application is in production.
     */
    public function isProduction(): bool;

    /**
     * Check if the application is in development.
     */
    public function isDevelopment(): bool;

    /**
     * Check if the application is in local environment.
     */
    public function isLocal(): bool;

    /**
     * Get the application version.
     */
    public function version(): string;
}
