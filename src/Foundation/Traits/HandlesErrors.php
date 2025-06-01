<?php

namespace Axcel\Foundation\Traits;

trait HandlesErrors
{
    private function registerErrorHandling(): void
    {
        if ($this->environment() === 'development' || $this->appDebug()) {
            $this->registerDevelopmentErrorHandler();
        } else {
            $this->registerProductionErrorHandler();
        }
    }

    private function registerDevelopmentErrorHandler(): void
    {
        // Use Whoops for detailed error reporting in development
        $whoops = new \Whoops\Run;
        $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
        $whoops->register();
    }

    private function registerProductionErrorHandler(): void
    {
        set_exception_handler(function (\Throwable $e) {
            $this->handleProductionError($e);
        });
    }

    private function handleProductionError(\Throwable $e): void
    {
        http_response_code(500);
        error_log($this->formatErrorForLog($e));

        // Try to render error view with graceful fallback
        $errorContent = $this->renderErrorView('errors.500') ?? $this->defaultErrorPage();

        echo $errorContent;
        exit;
    }

    public function renderNotFound(): void
    {
        http_response_code(404);

        if ($this->environment() === 'development' || $this->appDebug()) {
            // In development, show more detailed 404 info
            $errorContent = $this->renderDevelopment404() ?? $this->renderErrorView('errors.404') ?? $this->default404Page();
        } else {
            // In production, use standard error view handling
            $errorContent = $this->renderErrorView('errors.404') ?? $this->default404Page();
        }

        echo $errorContent;
        exit;
    }

    /**
     * Safely attempt to render an error view with fallback
     */
    private function renderErrorView(string $viewName): ?string
    {
        try {
            if (!$this->has('view')) {
                return null;
            }

            $view = $this->get('view');

            // Check if view exists before attempting to render
            if (method_exists($view, 'exists') && !$view->exists($viewName)) {
                return null;
            }

            return $view->make($viewName)->render();
        } catch (\Throwable $viewError) {
            // Log view rendering errors
            error_log("Error rendering view '{$viewName}': " . $viewError->getMessage());

            // In development, you might want to show this error too
            if ($this->environment() === 'development' || $this->appDebug()) {
                error_log("View error stack trace: " . $viewError->getTraceAsString());
            }

            return null;
        }
    }

    /**
     * Enhanced 404 page for development with debugging info
     */
    private function renderDevelopment404(): ?string
    {
        if (!($this->environment() === 'development' || $this->appDebug())) {
            return null;
        }

        $debugInfo = $this->gather404DebugInfo();

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>404 - Page Not Found (Development)</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; background: #f8f9fa; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #dc3545; margin-bottom: 20px; }
        .debug-info { background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 20px 0; }
        .debug-info h3 { margin-top: 0; color: #495057; }
        .debug-info pre { background: #e9ecef; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .routes { margin: 20px 0; }
        .route { padding: 8px; margin: 4px 0; background: #e3f2fd; border-left: 4px solid #2196f3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>404 - Page Not Found</h1>
        <p>The requested URL <strong>{$debugInfo['url']}</strong> was not found.</p>
        
        <div class="debug-info">
            <h3>Request Information</h3>
            <pre>Method: {$debugInfo['method']}
URL: {$debugInfo['url']}
Time: {$debugInfo['timestamp']}</pre>
        </div>

        {$debugInfo['routes_html']}

        <div class="debug-info">
            <h3>Possible Solutions</h3>
            <ul>
                <li>Check if the route is properly defined</li>
                <li>Verify the URL spelling and case sensitivity</li>
                <li>Ensure the controller and method exist</li>
                <li>Check middleware that might be blocking the route</li>
            </ul>
        </div>
    </div>
</body>
</html>
HTML;
    }


    public function privilegeError()
    {
        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>403 - Access Denied</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background: #f4f6f8;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        .container {
            background: #fff;
            max-width: 480px;
            width: 90%;
            padding: 40px 30px;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
            text-align: center;
        }
        h1 {
            font-size: 3rem;
            margin-bottom: 10px;
            color: #e53e3e;
        }
        p {
            font-size: 1.125rem;
            margin-bottom: 25px;
            color: #555;
        }
        a.button {
            display: inline-block;
            padding: 12px 28px;
            background: #3182ce;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }
        a.button:hover {
            background: #2c5282;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>403</h1>
        <p>Oops! You do not have permission to access this page.</p>
    </div>
</body>
</html>
HTML;
        exit;
    }


    /**
     * Gather debugging information for 404 errors
     */
    private function gather404DebugInfo(): array
    {
        $info = [
            'url' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
            'timestamp' => date('Y-m-d H:i:s'),
            'routes_html' => ''
        ];

        // Try to get available routes for debugging
        try {
            if ($this->has('router')) {
                $router = $this->get('router');
                if (method_exists($router, 'getRoutes')) {
                    $routes = $router->getRoutes();
                    $routesHtml = '<div class="debug-info"><h3>Available Routes</h3><div class="routes">';

                    foreach (array_slice($routes, 0, 10) as $route) { // Limit to first 10
                        $method = $route['method'] ?? 'GET';
                        $uri = $route['uri'] ?? $route['path'] ?? 'Unknown';
                        $routesHtml .= "<div class='route'>{$method} {$uri}</div>";
                    }

                    if (count($routes) > 10) {
                        $routesHtml .= "<p>... and " . (count($routes) - 10) . " more routes</p>";
                    }

                    $routesHtml .= '</div></div>';
                    $info['routes_html'] = $routesHtml;
                }
            }
        } catch (\Throwable $e) {
            // Silently fail if we can't get routes
        }

        return $info;
    }

    /**
     * Enhanced error logging format
     */
    private function formatErrorForLog(\Throwable $e): string
    {
        $context = [
            'url' => $_SERVER['REQUEST_URI'] ?? 'cli',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'cli',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];

        return sprintf(
            "[%s] %s: %s in %s:%d | Context: %s",
            date('Y-m-d H:i:s'),
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            json_encode($context)
        );
    }

    private function defaultErrorPage(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Error</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            text-align: center; 
            padding: 50px 20px; 
            background: linear-gradient(135deg,rgb(27, 44, 51) 0%,rgb(49, 32, 66) 100%);
            color: white;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container {
            background: rgba(255,255,255,0.1);
            padding: 40px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
            max-width: 500px;
            margin-top: 6rem;
        }
        h1 { font-size: 48px; margin-bottom: 20px; font-weight: 300; }
        p { font-size: 18px; opacity: 0.9; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>Oops!</h1>
        <p>Something went wrong on our end. We're working to fix it.</p>
        <p>Please try again in a few moments.</p>
    </div>
</body>
</html>
HTML;
    }

    private function default404Page(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            text-align: center; 
            padding: 0; 
            background: linear-gradient(135deg,rgb(15, 18, 27) 0%,rgb(19, 65, 151) 100%);
            color: white;
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container {
            background: rgba(255,255,255,0.1);
            padding: 40px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
            max-width: 500px;
        }
        h1 { font-size: 72px; margin-bottom: 20px; font-weight: 300; }
        p { font-size: 18px; opacity: 0.9; line-height: 1.6; }
        a { color: white; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>404</h1>
        <p>The page you're looking for doesn't exist.</p>
        <p><a href="/">Return to homepage</a></p>
    </div>
</body>
</html>
HTML;
    }
}
