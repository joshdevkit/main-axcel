<?php

namespace Axcel\Console;

abstract class Command
{
    protected string $signature = '';
    protected string $description = '';

    abstract public function handle(array $arguments = []): void;

    protected function info(string $message): void
    {
        echo "[INFO] {$message}\n";
    }

    protected function error(string $message): void
    {
        echo "[ERROR] {$message}\n";
    }

    protected function success(string $message): void
    {
        echo "[SUCCESS] {$message}\n";
    }

    protected function warn(string $message): void
    {
        echo "[WARNING] {$message}\n";
    }

    protected function line(string $message): void
    {
        echo "{$message}\n";
    }

    public function call(string $command, array $arguments = []): void
    {
        $kernel = app(ConsoleKernel::class);
        $kernel->handle(array_merge([$command], $arguments));
    }
}
