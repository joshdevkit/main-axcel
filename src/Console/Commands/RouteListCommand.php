<?php

namespace Axcel\Console\Commands;

use Axcel\Console\Command;
use Axcel\Routing\Router;

class RouteListCommand extends Command
{
    protected string $signature = 'route:list';
    protected string $description = 'List all registered routes';

    public function handle(array $arguments = []): void
    {
        $routes = app()->get(Router::class)->getRoutes();

        if (empty($routes)) {
            $this->warn("No routes registered.");
            return;
        }

        $headers = ['Method', 'URI', 'Name', 'Action', 'Middleware'];
        $rows = [];

        foreach ($routes as $route) {
            $method = implode(',', $route->getMethods());
            $method = colorizeMethod($method);
            $uri = $route->getUri();
            $name = method_exists($route, 'getName') ? $route->getName() : '-';

            $action = $route->getAction();
            if (is_array($action) && isset($action[0], $action[1])) {
                $controller = is_string($action[0]) ? $action[0] : get_class($action[0]);
                $methodName = $action[1];
                $prefix = 'App\\Http\\Controllers\\';
                if (str_starts_with($controller, $prefix)) {
                    $controller = substr($controller, strlen($prefix));
                }
                $action = "{$controller}@{$methodName}";
            } elseif ($action instanceof \Closure) {
                $action = 'Closure';
            } elseif (is_string($action)) {
                $action = $action;
            } else {
                $action = '{}';
            }

            $middlewares = method_exists($route, 'getMiddlewares') ? $route->getMiddlewares() ?? [] : [];
            $middlewares = array_unique($middlewares);
            $middlewares = !empty($middlewares) ? implode(',', $middlewares) : '-';

            $rows[] = [$method, $uri, $name ?: '-', $action, $middlewares];
        }

        $this->printTable($headers, $rows);
    }


    private function printTable(array $headers, array $rows): void
    {
        $lengths = array_map(function ($header) {
            return strlen($header);
        }, $headers);

        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $lengths[$i] = max($lengths[$i], strlen($cell));
            }
        }

        $divider = '+' . implode('+', array_map(fn($len) => str_repeat('-', $len + 2), $lengths)) . '+';
        $printRow = function ($row) use ($lengths) {
            echo '|';
            foreach ($row as $i => $cell) {
                printf(" %-{$lengths[$i]}s |", $cell);
            }
            echo PHP_EOL;
        };

        echo $divider . PHP_EOL;
        $printRow($headers);
        echo $divider . PHP_EOL;
        foreach ($rows as $row) {
            $printRow($row);
        }
        echo $divider . PHP_EOL;
    }
}


function colorizeMethod(string $method): string
{
    $colors = [
        'GET' => "\033[32m",    // Green
        'POST' => "\033[33m",   // Yellow
        'PUT' => "\033[34m",    // Blue
        'PATCH' => "\033[36m",  // Cyan
        'DELETE' => "\033[31m", // Red
    ];
    $reset = "\033[0m";

    $methods = explode(',', $method);
    foreach ($methods as &$m) {
        $m = ($colors[$m] ?? '') . $m . $reset;
    }

    return implode(',', $methods);
}
