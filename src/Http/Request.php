<?php

namespace Axcel\Http;

use Axcel\Validation\Validator;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;


class Request extends SymfonyRequest
{
    /**
     * Create a new request instance from globals
     */
    public static function capture(): self
    {
        static::enableHttpMethodParameterOverride();
        return static::createFromGlobals();
    }

    /**
     * Create a new request instance from Symfony request
     */
    public static function createFromSymfonyRequest(SymfonyRequest $request): self
    {
        $newRequest = new static(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all(),
            $request->cookies->all(),
            $request->files->all(),
            $request->server->all(),
            $request->getContent()
        );

        $newRequest->headers->replace($request->headers->all());

        return $newRequest;
    }

    /**
     * Get all input data (query + request parameters)
     */
    public function all(): array
    {
        $data = array_merge($this->query->all(), $this->request->all());

        // Add files keys as well
        foreach ($this->files->all() as $key => $file) {
            // For validation, you can add the file object or just a placeholder
            $data[$key] = $file;
        }

        return $data;
    }


    /**
     * Get input value by key
     */
    public function input(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->all();
        }

        return $this->get($key, $default);
    }

    /**
     * Get input value using dot notation
     */
    public function get(string $key, mixed $default = null, bool $deep = false): mixed
    {
        // Check if using dot notation
        if (strpos($key, '.') !== false) {
            return $this->getFromDotNotation($key, $default);
        }

        // First check POST data, then GET data
        if ($this->request->has($key)) {
            return $this->request->get($key, $default, $deep);
        }

        return $this->query->get($key, $default, $deep);
    }

    /**
     * Get value using dot notation (e.g., 'user.name' or 'data.items.0.title')
     */
    private function getFromDotNotation(string $key, $default = null)
    {
        $data = $this->all();
        $keys = explode('.', $key);

        foreach ($keys as $segment) {
            if (is_array($data) && array_key_exists($segment, $data)) {
                $data = $data[$segment];
            } else {
                return $default;
            }
        }

        return $data;
    }

    /**
     * Check if input has a value
     */
    public function has(string $key): bool
    {
        if (strpos($key, '.') !== false) {
            return $this->getFromDotNotation($key) !== null;
        }

        return $this->request->has($key) || $this->query->has($key);
    }

    /**
     * Check if input is filled (not null and not empty string)
     */
    public function filled(string $key): bool
    {
        $value = $this->get($key);
        return $value !== null && $value !== '';
    }

    /**
     * Get only specified keys from input
     */
    public function only(array $keys): array
    {
        $data = [];
        foreach ($keys as $key) {
            if ($this->has($key)) {
                $data[$key] = $this->get($key);
            }
        }
        return $data;
    }

    /**
     * Get all input except specified keys
     */
    public function except(array $keys): array
    {
        $data = $this->all();
        foreach ($keys as $key) {
            unset($data[$key]);
        }
        return $data;
    }

    /**
     * Get JSON data from request body
     */
    public function json(?string $key = null, $default = null)
    {
        if (!$this->isJson()) {
            return $default;
        }

        $data = json_decode($this->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $default;
        }

        if ($key === null) {
            return $data;
        }

        if (strpos($key, '.') !== false) {
            return $this->getFromArrayWithDotNotation($data, $key, $default);
        }

        return $data[$key] ?? $default;
    }

    /**
     * Check if request expects JSON response
     */
    public function expectsJson(): bool
    {
        return $this->isJson() || $this->wantsJson();
    }

    /**
     * Check if request wants JSON response
     */
    public function wantsJson(): bool
    {
        $acceptable = $this->getAcceptableContentTypes();
        return isset($acceptable[0]) && str_contains($acceptable[0], 'json');
    }

    /**
     * Check if request is JSON
     */
    public function isJson(): bool
    {
        return str_contains($this->headers->get('content-type', ''), 'json');
    }

    /**
     * Check if request is AJAX
     */
    public function ajax(): bool
    {
        return $this->isXmlHttpRequest();
    }

    /**
     * Get the bearer token from the request
     */
    public function bearerToken(): ?string
    {
        $header = $this->headers->get('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }

    /**
     * Get user IP address
     */
    public function ip(): string
    {
        return $this->getClientIp() ?? '127.0.0.1';
    }

    /**
     * Get user agent
     */
    public function userAgent(): string
    {
        return $this->headers->get('User-Agent', '');
    }

    /**
     * Get current URL
     */
    public function url(): string
    {
        return $this->getSchemeAndHttpHost() . $this->getRequestUri();
    }

    /**
     * Get full URL including query string
     */
    public function fullUrl(): string
    {
        return $this->url();
    }

    /**
     * Check if current path matches pattern
     */
    public function is(string $pattern): bool
    {
        $path = '/' . trim($this->getPathInfo(), '/');
        $pattern = '/' . trim($pattern, '/');

        // Convert wildcard pattern to regex
        $pattern = preg_quote($pattern, '#');
        $pattern = str_replace('\*', '.*', $pattern);

        return (bool) preg_match('#^' . $pattern . '$#', $path);
    }

    /**
     * Get route parameters (to be set by router)
     */
    public function route(?string $key = null, $default = null)
    {
        $routeParams = $this->attributes->get('_route_params', []);

        if ($key === null) {
            return $routeParams;
        }

        return $routeParams[$key] ?? $default;
    }

    /**
     * Set route parameters (used by router)
     */
    public function setRouteParameters(array $parameters): void
    {
        $this->attributes->set('_route_params', $parameters);
    }

    /**
     * Validate input data
     */
    /**
     * Validate input data
     */
    public function validate(array $rules, array $messages = [], array $attributes = []): array
    {
        $validator = new Validator($this->all(), $rules, $messages, $attributes);
        if ($validator->fails()) {
            $errors = $validator->getErrors();
            $message = $validator->buildFirstErrorMessage($errors);
            throw new ValidationException($errors, $message);
        }
        return $validator->getValidatedData();
    }


    /**
     * Get value from array using dot notation
     */
    private function getFromArrayWithDotNotation(array $array, string $key, $default = null)
    {
        $keys = explode('.', $key);

        foreach ($keys as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }

        return $array;
    }

    /**
     * Get file from request
     */
    public function file(string $key): ?\Symfony\Component\HttpFoundation\File\UploadedFile
    {
        return $this->files->get($key);
    }

    /**
     * Check if request has file
     */
    public function hasFile(string $key): bool
    {
        $file = $this->file($key);
        return $file !== null && $file->isValid();
    }

    /**
     * Get cookie value
     */
    public function cookie(string $key, $default = null)
    {
        return $this->cookies->get($key, $default);
    }

    /**
     * Get header value
     */
    public function header(string $key, $default = null): ?string
    {
        return $this->headers->get($key, $default);
    }

    /**
     * Get server variable
     */
    public function server(string $key, $default = null)
    {
        return $this->server->get($key, $default);
    }

    /**
     * Get query parameter
     */
    public function query(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->query->all();
        }

        return $this->query->get($key, $default);
    }

    /**
     * Get POST parameter
     */
    public function post(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->request->all();
        }

        return $this->request->get($key, $default);
    }

    /**
     * Convert request to array (for debugging)
     */
    public function toArray(): array
    {
        return [
            'method' => $this->getMethod(),
            'url' => $this->url(),
            'headers' => $this->headers->all(),
            'query' => $this->query->all(),
            'request' => $this->request->all(),
            'files' => array_map(function ($file) {
                return [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'type' => $file->getClientMimeType(),
                ];
            }, $this->files->all()),
            'cookies' => $this->cookies->all(),
            'ip' => $this->ip(),
            'user_agent' => $this->userAgent(),
        ];
    }
}
