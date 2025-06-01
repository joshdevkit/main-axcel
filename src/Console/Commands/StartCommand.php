<?php

namespace Axcel\Console\Commands;

use Axcel\Console\Command;

class StartCommand extends Command
{
    protected string $signature = 'start';
    protected string $description = 'Start the development server';

    public function handle(array $arguments = []): void
    {

        $publicPath = public_path();

        if (!is_dir($publicPath)) {
            $this->error("Public directory not found at: $publicPath");
            return;
        }

        $this->info("Starting development server...");

        $cmd = sprintf('php -S %s:%s -t %s', "127.0.0.1", "8000", $publicPath);
        passthru($cmd);
    }
}
