<?php

namespace Axcel\Console\Commands;

use Axcel\Console\Command;
use Axcel\Foundation\Application;

class MigrateRollbackCommand extends Command
{
    protected string $signature = 'migrate:rollback';
    protected string $description = 'Rollback the last migrations';

    public function handle(array $args = []): void
    {
        try {
            $this->line("🔁 Rolling back last migration batch...\n");

            $app = app();
            $migrator = $app->get('migrator');

            $rolledBackMigrations = $migrator->rollback();

            if (empty($rolledBackMigrations)) {
                $this->warn("⚠️  Nothing to rollback.");
            } else {
                foreach ($rolledBackMigrations as $migrationName) {
                    $this->line("Rolling back: {$migrationName}...");
                    $this->success("✅ Rolled back: {$migrationName}");
                }

                $this->line("\n🎉 Rollback completed successfully!");
            }
        } catch (\Exception $e) {
            $this->error("❌ Migration rollback failed: " . $e->getMessage());
        }
    }
}
