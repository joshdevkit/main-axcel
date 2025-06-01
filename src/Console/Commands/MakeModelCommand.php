<?php

namespace Axcel\Console\Commands;

use Axcel\Console\Command;
use Axcel\Support\Str;

class MakeModelCommand extends Command
{
    protected string $signature = 'make:model';
    protected string $description = 'Create a new model class';

    public function handle(array $arguments = []): void
    {
        $name = $arguments[0] ?? null;

        if (!$name) {
            $this->error("Model name is required.");
            return;
        }

        $modelPath = app()->basePath("app/Models/{$name}.php");

        if (!is_dir(dirname($modelPath))) {
            mkdir(dirname($modelPath), 0755, true);
        }

        $content = "<?php

namespace App\Models;

class {$name}
{
    // Model code here
}
";

        file_put_contents($modelPath, $content);
        $this->success("Model created: app/Models/{$name}.php");

        if (in_array('-m', $arguments)) {
            $migrationName = "create_" . Str::snake(Str::pluralStudly($name)) . "_table";
            $this->call('make:migration', [$migrationName]);
        }
    }


    protected function generateModelStub(string $className): string
    {
        return <<<PHP
<?php

namespace App\Models;

use Axcel\Database\Model;

class {$className} extends Model
{
    //
}

PHP;
    }
}
