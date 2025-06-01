<?php

use Axcel\Facades\Config;
use Axcel\Support\Carbon;
use Axcel\Support\Collection;

if (!function_exists('app')) {
    /**
     * Get the application instance
     */
    function app(?string $abstract = null)
    {
        $app = \Axcel\Foundation\Application::getInstance();

        if ($abstract) {
            return $app->get($abstract);
        }

        return $app;
    }
}


if (!function_exists('config')) {
    function config(?string $key = null, $default = null)
    {
        return Config::get($key, $default);
    }
}


// if (!function_exists('class_basename')) {
//     function class_basename($class)
//     {
//         $class = is_object($class) ? get_class($class) : $class;
//         $base = basename(str_replace('\\', '/', $class));
//         if (str_ends_with($base, 's') && strlen($base) > 1) {
//             $base = rtrim($base, 's');
//         }

//         return $base;
//     }
// }


if (!function_exists('database_path')) {
    /**
     * Get the path to the database directory.
     *
     * @param  string  $path
     * @return string
     */
    function database_path($path = '')
    {
        $app = app();
        $databasePath = $app->basePath('database');

        return $path ? $databasePath . '/' . ltrim($path, '/') : $databasePath;
    }
}

if (!function_exists('request')) {
    /**
     * Get the request instance
     */
    function request(?string $key = null, $default = null)
    {
        $request = app('request');

        if ($key === null) {
            return $request;
        }

        return $request->input($key, $default);
    }
}

if (!function_exists('response')) {
    /**
     * Return a new response from the application
     */
    function response($content = '', int $status = 200, array $headers = [])
    {
        return new Axcel\Http\Response($content, $status, $headers);
    }
}

if (!function_exists('abort')) {
    /**
     * Abort the request with the given status code and optional message.
     *
     * @param  int  $code
     * @param  string|null  $message
     * @param  array  $headers
     * @return void
     *
     * @throws \Axcel\Exceptions\HttpException
     */
    function abort(int $code = 500, ?string $message = null, array $headers = []): void
    {
        if (app()->isDevelopment()) {
            throw new \Axcel\Exceptions\HttpException($code, $message, null, $headers);
        }

        app()->privilegeError();
    }
}


if (!function_exists('route')) {
    function route(string $name, array $parameters = []): string
    {
        $router = app('route');

        try {
            return $router->url($name, $parameters);
        } catch (\InvalidArgumentException $e) {
            throw new \RuntimeException("Route generation failed for '{$name}': " . $e->getMessage());
        }
    }
}


if (!function_exists('redirect')) {
    /**
     * Get an instance of the redirector
     */
    function redirect(?string $to = null, int $status = 302, array $headers = [], ?bool $secure = null)
    {
        if ($to === null) {
            return app('redirect');
        }
        return app('redirect')->to($to, $status, $headers, $secure);
    }
}

if (!function_exists('url')) {
    /**
     * Generate a url for the application
     */
    function url(?string $path = null, array $parameters = [], ?bool $secure = null): string
    {
        $generator = app('url');

        if ($path === null) {
            return $generator;
        }

        return $generator->to($path, $parameters, $secure);
    }
}



if (!function_exists('auth')) {
    function auth()
    {
        return app('auth');
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        // Parse boolean-like strings
        return match (strtolower($value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}



if (!function_exists('view')) {
    function view(string $view, array $data = []): string
    {
        return app('view')->make($view, $data);
    }
}


if (!function_exists('class_basename')) {
    /**
     * Get the class "basename" of the given object or class name.
     *
     * @param  object|string  $class
     * @return string
     */
    function class_basename($class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        // Normalize to string and extract the base name
        $class = trim($class, '\\'); // remove leading backslash if any
        if (false !== $pos = strrpos($class, '\\')) {
            return substr($class, $pos + 1);
        }
        return $class;
    }
}

if (!function_exists('str_singular')) {
    function str_singular($value)
    {
        return rtrim($value, 's');
    }
}

if (!function_exists('collect')) {
    /**
     * Create a new collection from the given value.
     *
     * @param  array  $items
     * @return Collection
     */
    function collect(array $items): Collection
    {
        return new Collection($items);
    }
}


if (!function_exists('carbon')) {
    /**
     * Create a new Carbon instance
     */
    function carbon($time = null, $timezone = null): Carbon
    {
        return new Carbon($time, $timezone);
    }
}

if (!function_exists('now')) {
    /**
     * Get current date/time as Carbon instance
     */
    function now($timezone = null): Carbon
    {
        return Carbon::now($timezone);
    }
}

if (!function_exists('today')) {
    /**
     * Get today's date at midnight
     */
    function today($timezone = null): Carbon
    {
        return Carbon::today($timezone);
    }
}

if (!function_exists('tomorrow')) {
    /**
     * Get tomorrow's date at midnight
     */
    function tomorrow($timezone = null): Carbon
    {
        return Carbon::tomorrow($timezone);
    }
}

if (!function_exists('yesterday')) {
    /**
     * Get yesterday's date at midnight
     */
    function yesterday($timezone = null): Carbon
    {
        return Carbon::yesterday($timezone);
    }
}


if (!function_exists('notEmpty')) {
    function notEmpty($value): bool
    {
        if (is_null($value)) return false;

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return !empty($value);
        }

        return !empty($value); // covers numbers, objects, booleans
    }
}


if (!function_exists('storage_path')) {
    /**
     * Get the path to the storage folder.
     *
     * @param  string  $path
     * @return string
     */
    function storage_path($path = '')
    {
        return app()->storagePath($path);
    }
}


if (!function_exists('public_path')) {
    /**
     * Get the path to the public folder.
     *
     * @param  string  $path
     * @return string
     */
    function public_path($path = '')
    {
        return app()->publicPath($path);
    }
}
