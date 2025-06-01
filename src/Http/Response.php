<?php

namespace Axcel\Http;

class Response
{
    protected $content;
    protected int $statusCode;
    protected array $headers;
    protected string $statusText;

    protected static array $statusTexts = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        304 => 'Not Modified',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        419 => 'Invalid CSRF Token',
        422 => 'Unprocessable Content',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
    ];

    public function __construct($content = '', int $status = 200, array $headers = [])
    {
        $this->setContent($content);
        $this->setStatusCode($status);
        $this->headers = $headers;
    }

    public static function make($content = '', int $status = 200, array $headers = []): self
    {
        return new static($content, $status, $headers);
    }

    public function setContent($content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function setStatusCode(int $code, ?string $text = null): self
    {
        $this->statusCode = $code;
        $this->statusText = $text ?? static::$statusTexts[$code] ?? 'Unknown';
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getStatusText(): string
    {
        return $this->statusText;
    }

    public function setHeader(string $name, $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function addHeader(string $name, $value): self
    {
        if (isset($this->headers[$name])) {
            if (!is_array($this->headers[$name])) {
                $this->headers[$name] = [$this->headers[$name]];
            }
            $this->headers[$name][] = $value;
        } else {
            $this->headers[$name] = $value;
        }
        return $this;
    }

    public function getHeader(string $name, $default = null)
    {
        return $this->headers[$name] ?? $default;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[$name]);
    }

    public function removeHeader(string $name): self
    {
        unset($this->headers[$name]);
        return $this;
    }

    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function json($data, int $status = 200, array $headers = [], int $options = 0): self
    {
        $options |= JSON_PRETTY_PRINT;

        $this->setContent(json_encode($data, $options));
        $this->setStatusCode($status);
        $this->setHeader('Content-Type', 'application/json');
        $this->withHeaders($headers);

        return $this;
    }


    public function redirect(string $url, int $status = 302): self
    {
        $this->setStatusCode($status);
        $this->setHeader('Location', $url);
        return $this;
    }

    public function view(string $view, array $data = []): self
    {
        $viewFactory = app('view');
        $content = $viewFactory->make($view, $data)->render();
        $this->setContent($content);
        $this->setHeader('Content-Type', 'text/html');

        return $this;
    }

    public function download(string $filePath, ?string $name = null, array $headers = []): self
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $fileName = $name ?? basename($filePath);
        $mimeType = $this->getMimeType($filePath);

        $this->setContent(file_get_contents($filePath));
        $this->setHeader('Content-Type', $mimeType);
        $this->setHeader('Content-Disposition', "attachment; filename=\"{$fileName}\"");
        $this->setHeader('Content-Length', filesize($filePath));
        $this->withHeaders($headers);

        return $this;
    }

    public function file(string $filePath, array $headers = []): self
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $mimeType = $this->getMimeType($filePath);

        $this->setContent(file_get_contents($filePath));
        $this->setHeader('Content-Type', $mimeType);
        $this->setHeader('Content-Length', filesize($filePath));
        $this->withHeaders($headers);

        return $this;
    }

    public function cookie(
        string $name,
        string $value,
        int $minutes = 0,
        ?string $path = null,
        ?string $domain = null,
        bool $secure = false,
        bool $httpOnly = true
    ): self {
        $expire = $minutes > 0 ? time() + ($minutes * 60) : 0;

        setcookie(
            $name,
            $value,
            $expire,
            $path ?? '/',
            $domain,
            $secure,
            $httpOnly
        );

        return $this;
    }

    public function withCookie(
        string $name,
        string $value,
        int $minutes = 0,
        ?string $path = null,
        ?string $domain = null,
        bool $secure = false,
        bool $httpOnly = true
    ): self {
        return $this->cookie($name, $value, $minutes, $path, $domain, $secure, $httpOnly);
    }

    public function send(): void
    {
        $this->sendHeaders();
        $this->sendContent();

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    protected function sendHeaders(): self
    {
        if (headers_sent()) {
            return $this;
        }

        // Send status line with both code and text
        $statusLine = "HTTP/1.1 {$this->statusCode} {$this->statusText}";
        header($statusLine, true, $this->statusCode);

        // Send headers
        foreach ($this->headers as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    header("{$name}: {$v}", false);
                }
            } else {
                header("{$name}: {$value}");
            }
        }

        return $this;
    }

    protected function sendContent(): self
    {
        echo $this->content;
        return $this;
    }

    protected function getMimeType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $mimeTypes = [
            'txt' => 'text/plain',
            'html' => 'text/html',
            'htm' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function isRedirection(): bool
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }

    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    public function isServerError(): bool
    {
        return $this->statusCode >= 500;
    }

    public function isError(): bool
    {
        return $this->isClientError() || $this->isServerError();
    }

    public function isOk(): bool
    {
        return $this->statusCode === 200;
    }

    public function isNotFound(): bool
    {
        return $this->statusCode === 404;
    }

    public function isForbidden(): bool
    {
        return $this->statusCode === 403;
    }

    public function isUnauthorized(): bool
    {
        return $this->statusCode === 401;
    }

    public function __toString(): string
    {
        return (string) $this->content;
    }
}
