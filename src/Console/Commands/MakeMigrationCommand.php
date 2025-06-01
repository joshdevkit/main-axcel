<?php

namespace Axcel\Console\Commands;

use Axcel\Console\Command;
use Axcel\Support\Str;

class MakeMigrationCommand extends Command
{
    protected string $signature = 'make:migration';
    protected string $description = 'Create a new migration file';

    public function handle(array $arguments = []): void
    {
        $name = $arguments[0] ?? null;

        if (!$name) {
            $this->error("Migration name is required.");
            return;
        }

        $migrationsPath = app()->basePath('database/migrations');

        $timestamp = date('Y_m_d_His');
        $fileName = $timestamp . '_' . $name . '.php';
        $filePath = $migrationsPath . '/' . $fileName;
        $stub = <<<PHP
<?php

use Axcel\Database\Migrations\Migration;
use Axcel\Database\Schema\Blueprint;
use Axcel\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$this->extractTableName($name)}', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$this->extractTableName($name)}');
    }
};

PHP;

        file_put_contents($filePath, $stub);

        $this->success("Migration created: database/migrations/{$fileName}");
    }

    protected function extractTableName(string $name): string
    {
        if (preg_match('/create_(.+)_table/', $name, $matches)) {
            return $matches[1];
        }

        return $name;
    }
}
