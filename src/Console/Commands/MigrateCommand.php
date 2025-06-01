<?php

namespace Axcel\Console\Commands;

use Axcel\Console\Command;

class MigrateCommand extends Command
{
    protected string $signature = 'migrate';
    protected string $description = 'Run migration files';

    public function handle(array $args = []): void
    {
        try {
            $this->line("Starting database migration...\n");

            $app = app();

            $migrator = $app->get('migrator');

            $migrations = $migrator->run();

            if (empty($migrations)) {
                $this->warn("⚠️  Nothing to migrate.");
            } else {
                foreach ($migrations as $migration) {
                    $this->success("✅ Migrated: {$migration}");
                }

                $this->line("\n🎉 All migrations completed successfully!");
            }
        } catch (\Exception $e) {
            $this->error("❌ Migration failed: " . $e->getMessage());
        }
    }
}
