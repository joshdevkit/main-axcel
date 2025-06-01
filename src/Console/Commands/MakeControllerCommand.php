<?php

namespace Axcel\Console\Commands;

use Axcel\Console\Command;

class MakeControllerCommand extends Command
{
    protected string $signature = 'make:controller';
    protected string $description = 'Create a new controller class';

    public function handle(array $arguments = []): void
    {
        $name = $arguments[0] ?? null;

        if (!$name) {
            $this->error("Controller name is required.");
            return;
        }

        $isResource = in_array('--resource', $arguments);
        $modelName = $this->extractModel($arguments);

        $controllerName = str_ends_with($name, 'Controller') ? $name : $name . 'Controller';
        $controllerPath = app()->basePath("app/Http/Controllers/{$controllerName}.php");

        if (!is_dir(dirname($controllerPath))) {
            mkdir(dirname($controllerPath), 0755, true);
        }

        $methods = $isResource
            ? $this->generateResourceMethods($modelName)
            : $this->generateDefaultIndexMethod();

        $modelImport = $modelName ? "use App\Models\\{$modelName};\n" : '';

        $content = <<<PHP
<?php

namespace App\Http\Controllers;
use Axcel\Http\Request;

{$modelImport}
class {$controllerName}
{
{$methods}
}

PHP;

        file_put_contents($controllerPath, $content);

        $this->success("Controller created: app/Http/Controllers/{$controllerName}.php");
    }

    private function generateDefaultIndexMethod(): string
    {
        return <<<PHP

    public function index()
    {
        //
    }

PHP;
    }

    private function generateResourceMethods(?string $model = null): string
    {
        $modelVar = $model ? '$' . lcfirst($model) : '$model';
        $modelType = $model ? "{$model} " : '';
        $request = '$request';
        return <<<PHP

    public function index()
    {
        //
    }

    public function create()
    {
        //
    }

    public function store()
    {
        //
    }

    public function show({$modelType}{$modelVar})
    {
        //
    }

    public function edit({$modelType}{$modelVar})
    {
        //
    }

    public function update(Request $request, {$modelType}{$modelVar})
    {
        //
    }

    public function destroy({$modelType}{$modelVar})
    {
        //
    }

PHP;
    }

    private function extractModel(array $arguments): ?string
    {
        $resourceIndex = array_search('--resource', $arguments);
        if ($resourceIndex !== false && isset($arguments[$resourceIndex + 1])) {
            $next = $arguments[$resourceIndex + 1];
            return str_starts_with($next, '-') ? null : $next;
        }

        return null;
    }
}
