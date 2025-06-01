<?php

namespace Axcel\Http\Response;

use Axcel\Http\Response;

class RedirectResponse extends Response
{
    protected string $targetUrl;
    protected array $flashData = [];

    public function __construct(string $url, int $status = 302, array $headers = [])
    {
        parent::__construct('', $status, $headers);
        $this->setTargetUrl($url);
        parent::send();
    }

    protected function setTargetUrl(string $url): self
    {
        $this->targetUrl = $url;
        $this->setHeader('Location', $url);
        return $this;
    }

    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }

    public function with(string $key, $value): self
    {
        $this->flashData[$key] = $value;
        // Store in session if available
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['_flash'][$key] = $value;
        }
        return $this;
    }

    public function withErrors($errors, string $key = 'default'): self
    {
        if (is_string($errors)) {
            $errors = [$errors];
        }

        $this->flashData['errors'][$key] = $errors;

        // Store in session if available
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['_flash']['errors'][$key] = $errors;
        }

        return $this;
    }

    public function withInput(array $input): self
    {
        $this->flashData['old'] = $input;

        // Store in session if available
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['_flash']['old'] = $input;
        }

        return $this;
    }

    public function getFlashData(): array
    {
        return $this->flashData;
    }


    protected function storeFlashData(): void
    {
        if (empty($this->flashData)) {
            return;
        }

        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Store flash data
        foreach ($this->flashData as $key => $value) {
            $_SESSION['_flash'][$key] = $value;
        }
    }

    public function __toString(): string
    {
        return "Redirect to: {$this->targetUrl}";
    }
}
