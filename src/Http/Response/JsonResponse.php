<?php

namespace Axcel\Http\Response;

use Axcel\Http\Response;

// JSON Response class
class JsonResponse extends Response
{
    public function __construct($data = null, int $status = 200, array $headers = [], int $options = 0)
    {
        parent::__construct('', $status, $headers);

        $this->setData($data, $options);
        $this->setHeader('Content-Type', 'application/json');
    }

    public static function make($data = null, int $status = 200, array $headers = [], int $options = 0): self
    {
        return new static($data, $status, $headers, $options);
    }

    public function setData($data, int $options = 0): self
    {
        $this->content = json_encode($data, $options);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Failed to encode data to JSON: ' . json_last_error_msg());
        }

        return $this;
    }

    public function getData(): array
    {
        return json_decode($this->content, true);
    }
}
