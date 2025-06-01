<?php

namespace Axcel\Foundation;

use Axcel\Container\Container;
use Axcel\Contracts\Hashing\Hasher;
use Axcel\Database\DatabaseManager;
use Axcel\Database\Model;
use Axcel\Foundation\Traits\HandlesErrors;
use Axcel\Hashing\BcryptHasher;
use Axcel\Http\Redirector;
use Axcel\Http\Request;
use Axcel\Routing\Router;
use Axcel\Support\Config;
use Axcel\Support\Database;
use Axcel\Support\Facade;
use Axcel\View\ViewFactory;
use Composer\Autoload\ClassLoader;
use Axcel\Session\SessionManager;
use function Axcel\Filesystem\join_paths;

class Application extends Container
{
    use HandlesErrors;

    private static ?Application $instance = null;
    // private Container $container;
    private Router $router;
    private Request $request;
    private array $middlewares = [];
    private bool $booted = false;
    private bool $envLoaded = false;
    private ?ClassLoader $composerLoader = null;
    /**
     * The custom storage path defined by the developer.
     *
     * @var string
     */
    protected $storagePath;


    protected const APPLICATION_VERSION = '1.0.0';

    private function __construct(string $basePath = '')
    {
        parent::__construct();
        $this->detectComposerLoader();
        $this->registerCoreBindings();
        $this->registerBasePath($basePath);
        $this->loadEnvironment();
    }

    public static function getInstance(string $basePath = ''): self
    {
        if (self::$instance === null) {
            self::$instance = new self($basePath);
        }
        return self::$instance;
    }

    /**
     * Detect and store Composer's ClassLoader for auto-discovery
     */
    private function detectComposerLoader(): void
    {
        // Get all registered autoloaders
        $autoloaders = spl_autoload_functions();

        foreach ($autoloaders as $autoloader) {
            if (
                is_array($autoloader) &&
                isset($autoloader[0]) &&
                $autoloader[0] instanceof ClassLoader
            ) {
                $this->composerLoader = $autoloader[0];
                break;
            }
        }
    }

    /**
     * Get Composer's ClassLoader instance
     */
    public function getComposerLoader(): ?ClassLoader
    {
        return $this->composerLoader;
    }

    /**
     * Auto-discover classes based on PSR-4 namespaces
     */
    public function discoverClasses(string $namespace, string $suffix = ''): array
    {
        if (!$this->composerLoader) {
            return [];
        }

        $classes = [];
        $prefixes = $this->composerLoader->getPrefixesPsr4();

        foreach ($prefixes as $prefix => $paths) {
            if (strpos($namespace, $prefix) === 0) {
                foreach ($paths as $path) {
                    $classes = array_merge($classes, $this->scanDirectory($path, $prefix, $suffix));
                }
            }
        }

        return $classes;
    }

    /**
     * Scan directory for classes
     */
    private function scanDirectory(string $path, string $namespace, string $suffix = ''): array
    {
        $classes = [];

        if (!is_dir($path)) {
            return $classes;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $relativePath = str_replace($path . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $relativePath = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
                $className = $namespace . str_replace('.php', '', $relativePath);

                if ($suffix === '' || str_ends_with($className, $suffix)) {
                    if (class_exists($className)) {
                        $classes[] = $className;
                    }
                }
            }
        }

        return $classes;
    }

    private function registerCoreBindings(): void
    {
        $this->instance(Container::class, $this);
        $this->instance('container', $this);
        $this->instance(Application::class, $this);
        $this->instance('app', $this);

        // Register Composer loader if available
        if ($this->composerLoader) {
            $this->instance(ClassLoader::class, $this->composerLoader);
            $this->instance('composer', $this->composerLoader);
        }
    }

    private function registerBasePath(string $basePath): void
    {
        $this->instance('path.base', $basePath ?: getcwd());
        $this->instance('path.app', $basePath . '/app');
        $this->instance('path.Axcel', $basePath . '/Axcel');
        $this->instance('path.public', $basePath . '/public');
        $this->instance('path.bootstrap', $basePath . '/bootstrap');
        $this->instance('path.config', $basePath . '/config');
        $this->instance('path.resources', $basePath . '/resources');
        $this->instance('path.storage', $basePath . '/storage');
        $this->instance('path.vendor', $basePath . '/vendor');
    }

    /**
     * Load environment variables from .env file
     */
    private function loadEnvironment(): void
    {
        if ($this->envLoaded) {
            return;
        }

        $envPath = $this->basePath('.env');

        if (file_exists($envPath)) {
            try {
                // Try Dotenv first (composer package)
                if (class_exists('\Dotenv\Dotenv')) {
                    $dotenv = \Dotenv\Dotenv::createImmutable($this->basePath());
                    $dotenv->load();
                } else {
                    // Fallback to manual parsing
                    $this->parseEnvFile($envPath);
                }

                $this->envLoaded = true;
            } catch (\Exception $e) {
                error_log("Failed to load environment: " . $e->getMessage());
            }
        }
    }

    /**
     * Manual environment file parser (fallback)
     */
    private function parseEnvFile(string $path): void
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments and empty lines
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            // Parse key=value pairs
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove quotes if present
                $value = trim($value, '"\'');

                // Set environment variable if not already set
                if (!isset($_ENV[$key]) && getenv($key) === false) {
                    $_ENV[$key] = $value;
                    putenv("$key=$value");
                }
            }
        }
    }

    /**
     * Get environment variable with optional default
     */
    public function env(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        // Convert common boolean and null strings
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'null':
            case '(null)':
                return null;
            case 'empty':
            case '(empty)':
                return '';
        }

        return $value;
    }


    public function router(): Router
    {
        if (!isset($this->router)) {
            $this->router = new Router($this);
            $this->instance(Router::class, $this->router);
            $this->instance('route', $this->router);
        }
        return $this->router;
    }

    public function request(): Request
    {
        if (!isset($this->request)) {
            $this->request = Request::capture();
            $this->instance(Request::class, $this->request);
            $this->instance('request', $this->request);
        }
        return $this->request;
    }

    public function boot(): self
    {
        if ($this->booted) {
            return $this;
        }
        // error_log("Booting application...");

        Facade::setApplication($this);
        $this->registerCoreServices();

        // Boot configuration AFTER core services are registered
        // This ensures helper functions are available
        $this->bootConfiguration();

        $this->registerErrorHandling();
        $this->bootRouter();
        $this->autoDiscoverServices();
        $this->registerApplicationMiddlewares(); // Register middlewares with router

        $this->booted = true;
        $this->loadRoutes();

        $dbManager = $this->get('db');
        Model::setConnectionResolver($dbManager);
        // Schema::setDatabaseManager($dbManager);
        return $this;
    }

    /**
     * Register application middlewares with the router
     */
    private function registerApplicationMiddlewares(): void
    {
        $router = $this->router();

        foreach ($this->middlewares as $name => $class) {
            $router->registerMiddleware($name, $class);
        }
    }

    /**
     * Auto-discover and register services, providers, etc.
     */
    private function autoDiscoverServices(): void
    {
        if (!$this->composerLoader) {
            return;
        }

        // Auto-discover Service Providers
        $this->discoverServiceProviders();

        // Auto-discover Middlewares
        $this->discoverMiddlewares();

        // Auto-discover Commands (for future CLI support)
        $this->discoverCommands();
    }

    /**
     * Auto-discover service providers
     */
    private function discoverServiceProviders(): void
    {
        $providers = $this->discoverClasses('App\\Providers\\', 'ServiceProvider');

        foreach ($providers as $provider) {
            if (class_exists($provider)) {
                $instance = new $provider($this);
                if (method_exists($instance, 'register')) {
                    $instance->register();
                }
                if (method_exists($instance, 'boot')) {
                    $instance->boot();
                }
            }
        }
    }

    /**
     * Auto-discover middlewares
     */
    private function discoverMiddlewares(): void
    {
        $middlewares = $this->discoverClasses('App\\Http\\Middleware\\', 'Middleware');
        $router = $this->router();

        foreach ($middlewares as $middleware) {
            $name = strtolower(str_replace(['App\\Http\\Middleware\\', 'Middleware'], '', $middleware));
            $router->registerMiddleware($name, $middleware);
        }
    }

    /**
     * Auto-discover commands
     */
    private function discoverCommands(): void
    {
        // Future implementation for CLI commands
        $commands = $this->discoverClasses('App\\Console\\Commands\\', 'Command');
        // Store for future CLI implementation
        $this->instance('console.commands', $commands);
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    private function bootRouter(): void
    {
        $router = $this->router();
        $router->pattern('id', '[0-9]+');
        $router->pattern('slug', '[a-z0-9-]+');
        $router->pattern('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}');
    }

    private function bootConfiguration(): void
    {
        $configPath = $this->basePath('config');

        if (!is_dir($configPath)) {
            $this->singleton('config', fn() => new Config([]));
            return;
        }

        $files = glob($configPath . '/*.php');
        $config = [];

        foreach ($files as $file) {
            $key = basename($file, '.php');

            // Provide application context to config files
            $app = $this;

            try {
                $configData = require $file;
                $config[$key] = $configData;
            } catch (\Throwable $e) {
                error_log("Failed to load config file {$file}: " . $e->getMessage());
                $config[$key] = [];
            }
        }

        $this->singleton('config', fn() => new Config($config));
    }

    private function registerCoreServices(): void
    {
        // Register the application instance first so helper functions work
        $this->instance('app', $this);

        $this->singleton('request', function () {
            return Request::capture();
        });

        $this->singleton('route', function () {
            return new Router($this);
        });

        $this->singleton('db', function () {
            $config = $this->get('config')->get('database', []);
            return new DatabaseManager($config);
        });

        $this->singleton('database', function () {
            return new Database($this->get('db'));
        });

        $this->singleton('view', function () {
            $viewsPath = $this->basePath('resources/views');
            return new ViewFactory($viewsPath);
        });

        $this->singleton('hash', function () {
            $config = $this->get('config')->get('hashing', []);
            return new BcryptHasher($config);
        });

        $this->singleton('session', function () {
            $config = $this->get('config')->get('session', []);
            $sessionManager = new SessionManager($config);
            $sessionManager->start(); // Ensure the session is started
            return $sessionManager;
        });

        $this->singleton('auth', function () {
            return new \Axcel\Contracts\Auth\AuthManager(
                $this->get('session'),
                $this->get('hash'),
                'App\\Models\\User'
            );
        });

        $this->singleton('redirect', function () {
            return new Redirector(
                $this->get('request'),
                $this->get('route')
            );
        });

        $this->singleton('mail', function () {
            $config = $this->get('config')->get('mail', []);
            $view = $this->get('view');
            return new \Axcel\Mail\MailManager($config, $view);
        });

        $this->singleton('migrator', function () {
            return new \Axcel\Database\Migrations\Migrator(
                $this->get('db'),
                $this
            );
        });

        // Register aliases
        $this->alias('request', Request::class);
        $this->alias('route', Router::class);
        $this->alias('view', ViewFactory::class);
        $this->alias('db', DatabaseManager::class);
        $this->alias('hash', Hasher::class);
        $this->alias('database', Database::class);
        $this->alias('redirect', Redirector::class);
        $this->alias('mail', \Axcel\Contracts\Mail\MailerInterface::class);
        $this->alias('session', \Axcel\Session\SessionManager::class);
        $this->alias('auth', \Axcel\Contracts\Auth\AuthManager::class);
        $this->alias('migrator', \Axcel\Database\Migrations\Migrator::class);
    }

    public function run(): void
    {
        if (!$this->booted) {
            $this->boot();
        }
        $request = $this->request();
        $router = $this->router();
        $router->dispatch($request);
    }

    public function middleware(string $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * Register middlewares with the application
     * 
     * @param array $middlewares Array of middleware name => class mappings
     * @return self
     */
    public function withMiddlewares(array $middlewares): self
    {
        $this->middlewares = array_merge($this->middlewares, $middlewares);
        return $this;
    }

    public function basePath(string $path = ''): string
    {
        $basePath = $this->get('path.base');
        return $path ? $basePath . '/' . ltrim($path, '/') : $basePath;
    }

    public function appPath(string $path = ''): string
    {
        $appPath = $this->get('path.app');
        return $path ? $appPath . '/' . ltrim($path, '/') : $appPath;
    }

    public function publicPath(string $path = ''): string
    {
        $publicPath = $this->get('path.public');
        return $path ? $publicPath . '/' . ltrim($path, '/') : $publicPath;
    }

    public function configPath(string $path = ''): string
    {
        $configPath = $this->get('path.config');
        return $path ? $configPath . '/' . ltrim($path, '/') : $configPath;
    }

    public function resourcePath(string $path = ''): string
    {
        $resourcePath = $this->get('path.resources');
        return $path ? $resourcePath . '/' . ltrim($path, '/') : $resourcePath;
    }

    /**
     * Get the path to the storage directory.
     *
     * @param  string  $path
     * @return string
     */
    public function storagePath($path = '')
    {
        if (isset($_ENV['Axcel_STORAGE_PATH'])) {
            return $this->joinPaths($this->storagePath ?: $_ENV['Axcel_STORAGE_PATH'], $path);
        }

        if (isset($_SERVER['Axcel_STORAGE_PATH'])) {
            return $this->joinPaths($this->storagePath ?: $_SERVER['Axcel_STORAGE_PATH'], $path);
        }

        return $this->joinPaths($this->storagePath ?: $this->basePath('storage'), $path);
    }

    /**
     * Join the given paths together.
     *
     * @param  string  $basePath
     * @param  string  $path
     * @return string
     */
    public function joinPaths($basePath, $path = '')
    {
        return join_paths($basePath, $path);
    }

    public function loadRoutes(string $path = ''): self
    {
        $basePath = $this->basePath();
        $routesPath = $path ?: $basePath . '/routes/web.php';

        if (file_exists($routesPath)) {
            require $routesPath;
        }

        $apiRoutesPath = $basePath . '/routes/api.php';
        if (file_exists($apiRoutesPath)) {
            $router = $this->router();
            $router->group(['prefix' => 'api'], function () use ($apiRoutesPath) {
                require $apiRoutesPath;
            });
        }

        return $this;
    }


    public function appDebug(): string
    {
        return config('app.debug', false);
    }

    public function environment(): string
    {
        return config('app.env') ?? $this->env('APP_ENV', 'production');
    }

    public function isProduction(): bool
    {
        return $this->environment() === 'production';
    }

    public function isDevelopment(): bool
    {
        return in_array($this->environment(), ['development', 'dev']);
    }

    public function isLocal(): bool
    {
        return $this->environment() === 'local';
    }

    public function version(): string
    {
        return self::APPLICATION_VERSION;
    }

    private function __clone() {}
    public function __wakeup() {}
}
