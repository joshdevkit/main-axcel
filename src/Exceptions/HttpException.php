<?php

namespace Axcel\Exceptions;

use Exception;

class HttpException extends Exception
{
    protected int $statusCode;
    protected array $headers;

    /**
     * Create a new HTTP exception instance.
     *
     * @param  int  $statusCode
     * @param  string|null  $message
     * @param  \Throwable|null  $previous
     * @param  array  $headers
     */
    public function __construct(
        int $statusCode = 500,
        ?string $message = null,
        ?\Throwable $previous = null,
        array $headers = []
    ) {
        parent::__construct($message ?? "HTTP Error {$statusCode}", $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    /**
     * Get the HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get the response headers.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
