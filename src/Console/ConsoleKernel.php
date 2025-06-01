<?php

namespace Axcel\Console;

use Axcel\Foundation\Application;
use Axcel\Console\Commands\MigrateCommand;
use Axcel\Console\Commands\StartCommand;
use Axcel\Console\Commands\MakeModelCommand;
use Axcel\Console\Commands\MakeControllerCommand;
use Axcel\Console\Commands\MakeMigrationCommand;
use Axcel\Console\Commands\MigrateRollbackCommand;
use Axcel\Console\Commands\RouteListCommand;

class ConsoleKernel
{
    protected Application $app;
    protected array $commands = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->registerCommands();
    }

    protected function registerCommands(): void
    {
        $this->commands = [
            'start' => StartCommand::class,
            'make:model' => MakeModelCommand::class,
            'make:controller' => MakeControllerCommand::class,
            'make:migration' => MakeMigrationCommand::class,
            'route:list' => RouteListCommand::class,
            'migrate' => MigrateCommand::class,
            'migrate:rollback' => MigrateRollbackCommand::class,
        ];
    }

    public function handle(array $arguments): void
    {
        // Remove script name if present
        if (isset($arguments[0]) && str_contains($arguments[0], 'dev')) {
            array_shift($arguments);
        }

        if (empty($arguments)) {
            $this->showHelp();
            return;
        }

        $commandName = $arguments[0];
        $commandArgs = array_slice($arguments, 1);

        if (!isset($this->commands[$commandName])) {
            echo "Unknown command: {$commandName}\n";
            $this->showHelp();
            return;
        }

        $commandClass = $this->commands[$commandName];

        if (!class_exists($commandClass)) {
            echo "Command class not found: {$commandClass}\n";
            return;
        }

        try {

            $command = new $commandClass();
            $command->handle($commandArgs);
        } catch (\Exception $e) {
            echo "Command failed: " . $e->getMessage() . "\n";

            if ($this->app->isDevelopment()) {
                echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
            }
        } catch (\Error $e) {
            echo "Command error: " . $e->getMessage() . "\n";

            if ($this->app->isDevelopment()) {
                echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
            }
        }
    }

    protected function showHelp(): void
    {
        echo "Dev CLI Tool v1.0.0\n";
        echo "Usage:\n";
        echo "  php dev <command> [arguments] [options]\n";
        echo "Available commands:\n";

        $groupedCommands = $this->groupCommands();

        foreach ($groupedCommands as $group => $commands) {
            if ($group !== 'General') {
                echo "  {$group}\n";
            }

            foreach ($commands as $name => $class) {
                if (class_exists($class)) {
                    $instance = new $class();
                    $description = property_exists($instance, 'description') ? $instance->description : 'No description available';

                    if ($group === 'General') {
                        echo "    {$name}" . str_repeat(' ', 20 - strlen($name)) . "{$description}\n";
                    } else {
                        echo "    {$name}" . str_repeat(' ', 20 - strlen($name)) . "{$description}\n";
                    }
                }
            }
        }
    }

    protected function groupCommands(): array
    {
        $grouped = [
            'General' => [],
            'Make' => [],
            'Route' => [],
        ];

        foreach ($this->commands as $name => $class) {
            if (str_starts_with($name, 'make:')) {
                $grouped['Make'][$name] = $class;
            } elseif (str_starts_with($name, 'route:')) {
                $grouped['Route'][$name] = $class;
            } else {
                $grouped['General'][$name] = $class;
            }
        }

        return $grouped;
    }
}
