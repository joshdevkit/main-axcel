<?php

namespace Axcel\Database\Schema;

use Axcel\Database\Schema\Definitions\ColumnDefinition;
use Axcel\Database\Schema\Definitions\IndexDefinition;
use Axcel\Database\Schema\Definitions\ForeignKeyDefinition;

class Blueprint
{
    protected string $table;
    protected array $columns = [];
    protected array $commands = [];
    protected array $foreignKeys = [];

    protected string $engine = 'InnoDB';
    protected string $charset = 'utf8mb4';
    protected string $collation = 'utf8mb4_unicode_ci';
    protected bool $temporary = false;

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    /**
     * Get the table name
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Add a command to the blueprint
     */
    protected function addCommand(string $name, array $parameters = []): mixed
    {
        $this->commands[] = array_merge(compact('name'), $parameters);
        return end($this->commands);
    }

    /**
     * Add a column to the blueprint
     */
    protected function addColumn(string $type, string $name, array $parameters = []): ColumnDefinition
    {
        $column = new ColumnDefinition([
            'type' => $type,
            'name' => $name
        ] + $parameters);

        $column->setBlueprint($this);
        $this->columns[$name] = $column;
        return $column;
    }

    // Column types
    public function id(string $name = 'id'): ColumnDefinition
    {
        return $this->bigInteger($name)->unsigned()->autoIncrement()->primary();
    }

    /**
     * Get the foreign key definitions.
     *
     * @return array
     */
    public function getForeignKeys(): array
    {
        return $this->foreignKeys ?? [];
    }

    public function increments(string $column): ColumnDefinition
    {
        $col = $this->addColumn('integer', $column, ['autoIncrement' => true, 'unsigned' => true]);
        $this->primary($column);
        return $col;
    }

    public function bigIncrements(string $column): ColumnDefinition
    {
        $columnDef = $this->addColumn('bigInteger', $column, [
            'autoIncrement' => true,
            'unsigned' => true,
        ]);

        $this->primary($column); // Ensure it adds a PRIMARY KEY command
        return $columnDef;
    }


    public function string(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn('string', $column, compact('length'));
    }

    public function text(string $column): ColumnDefinition
    {
        return $this->addColumn('text', $column);
    }

    public function longText(string $column): ColumnDefinition
    {
        return $this->addColumn('longText', $column);
    }

    public function mediumText(string $column): ColumnDefinition
    {
        return $this->addColumn('mediumText', $column);
    }

    public function tinyText(string $column): ColumnDefinition
    {
        return $this->addColumn('tinyText', $column);
    }

    public function integer(string $column): ColumnDefinition
    {
        return $this->addColumn('integer', $column);
    }

    public function tinyInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('tinyInteger', $column);
    }

    public function smallInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('smallInteger', $column);
    }

    public function mediumInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('mediumInteger', $column);
    }

    public function bigInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('bigInteger', $column);
    }

    public function unsignedInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('integer', $column, ['unsigned' => true]);
    }

    public function unsignedBigInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('bigInteger', $column, ['unsigned' => true]);
    }

    public function unsignedTinyInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('tinyInteger', $column, ['unsigned' => true]);
    }

    public function unsignedSmallInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('smallInteger', $column, ['unsigned' => true]);
    }

    public function unsignedMediumInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('mediumInteger', $column, ['unsigned' => true]);
    }

    public function float(string $column, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn('float', $column, compact('precision', 'scale'));
    }

    public function double(string $column, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn('double', $column, compact('precision', 'scale'));
    }

    public function decimal(string $column, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn('decimal', $column, compact('precision', 'scale'));
    }

    public function unsignedDecimal(string $column, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn('decimal', $column, compact('precision', 'scale') + ['unsigned' => true]);
    }

    public function boolean(string $column): ColumnDefinition
    {
        return $this->addColumn('boolean', $column);
    }

    public function date(string $column): ColumnDefinition
    {
        return $this->addColumn('date', $column);
    }

    public function datetime(string $column): ColumnDefinition
    {
        return $this->addColumn('datetime', $column);
    }

    public function time(string $column): ColumnDefinition
    {
        return $this->addColumn('time', $column);
    }

    public function timestamp(string $column): ColumnDefinition
    {
        return $this->addColumn('timestamp', $column);
    }

    public function timestamps(): void
    {
        $this->timestamp('created_at')->default('CURRENT_TIMESTAMP');
        $this->timestamp('updated_at')->nullable();
    }

    public function timestampsTz(): void
    {
        $this->timestampTz('created_at')->nullable();
        $this->timestampTz('updated_at')->nullable();
    }

    public function timestampTz(string $column): ColumnDefinition
    {
        return $this->addColumn('timestampTz', $column);
    }

    public function dateTimeTz(string $column): ColumnDefinition
    {
        return $this->addColumn('dateTimeTz', $column);
    }

    public function timeTz(string $column): ColumnDefinition
    {
        return $this->addColumn('timeTz', $column);
    }

    public function softDeletes(): void
    {
        $this->timestamp('deleted_at')->nullable();
    }

    public function softDeletesTz(): void
    {
        $this->timestampTz('deleted_at')->nullable();
    }

    public function json(string $column): ColumnDefinition
    {
        return $this->addColumn('json', $column);
    }

    public function jsonb(string $column): ColumnDefinition
    {
        return $this->addColumn('jsonb', $column);
    }

    public function uuid(string $column): ColumnDefinition
    {
        return $this->addColumn('uuid', $column);
    }

    public function binary(string $column): ColumnDefinition
    {
        return $this->addColumn('binary', $column);
    }

    public function enum(string $column, array $allowed): ColumnDefinition
    {
        return $this->addColumn('enum', $column, compact('allowed'));
    }

    public function set(string $column, array $allowed): ColumnDefinition
    {
        return $this->addColumn('set', $column, compact('allowed'));
    }

    // Foreign keys with Laravel-like constrained() method
    public function foreign(string|array $columns): ForeignKeyDefinition
    {
        $columns = (array) $columns;
        $foreign = new ForeignKeyDefinition(['columns' => $columns]);
        $this->addCommand('foreign', ['foreign' => $foreign]);
        return $foreign;
    }

    public function foreignId(string $column): ColumnDefinition
    {
        return $this->unsignedBigInteger($column);
    }

    public function foreignIdFor(string $model, ?string $column = null): ColumnDefinition
    {
        $column = $column ?? $this->guessForeignIdName($model);
        return $this->foreignId($column);
    }

    public function foreignUuid(string $column): ColumnDefinition
    {
        return $this->uuid($column);
    }

    public function constrained(?string $table = null, string $column = 'id'): ForeignKeyDefinition
    {
        if (!$this->columns) {
            throw new \RuntimeException("No column specified for foreign key constraint");
        }

        $lastColumn = end($this->columns);
        $foreignColumn = $lastColumn->getName();

        // If no table name is provided, guess it from the foreign key column name
        if (is_null($table)) {
            $table = str_replace('_id', '', $foreignColumn);
            // Pluralize the table name
            $table = substr($table, -1) === 's' ? $table : $table . 's';
        }

        $foreignKey = new ForeignKeyDefinition([
            'local_column' => $foreignColumn,
            'foreign_table' => $table,
            'foreign_column' => $column,
        ]);

        $this->foreignKeys[] = $foreignKey;

        return $foreignKey;
    }
    /**
     * Guess the foreign table name from the column name
     */
    protected function guessForeignTableName(string $column): string
    {
        // Remove '_id' suffix and pluralize
        $singular = str_replace('_id', '', $column);
        return $this->pluralize($singular);
    }

    /**
     * Guess the foreign ID column name from model class
     */
    protected function guessForeignIdName(string $model): string
    {
        $modelName = class_basename($model);
        return strtolower($modelName) . '_id';
    }

    /**
     * Simple pluralization (you might want to use a more robust solution)
     */
    protected function pluralize(string $word): string
    {
        $irregulars = [
            'person' => 'people',
            'child' => 'children',
            'foot' => 'feet',
            'tooth' => 'teeth',
        ];

        if (isset($irregulars[$word])) {
            return $irregulars[$word];
        }

        // Simple rules
        if (str_ends_with($word, 'y')) {
            return substr($word, 0, -1) . 'ies';
        }

        foreach (['s', 'sh', 'ch', 'x', 'z'] as $suffix) {
            if (str_ends_with($word, $suffix)) {
                return $word . 'es';
            }
        }

        return $word . 's';
    }

    // Indexes
    public function primary(string|array $columns, ?string $name = null): IndexDefinition
    {
        return $this->indexCommand('primary', $columns, $name);
    }

    public function unique(string|array $columns, ?string $name = null): IndexDefinition
    {
        return $this->indexCommand('unique', $columns, $name);
    }

    public function index(string|array $columns, ?string $name = null): IndexDefinition
    {
        return $this->indexCommand('index', $columns, $name);
    }

    public function fullText(string|array $columns, ?string $name = null): IndexDefinition
    {
        return $this->indexCommand('fulltext', $columns, $name);
    }

    public function spatialIndex(string|array $columns, ?string $name = null): IndexDefinition
    {
        return $this->indexCommand('spatial', $columns, $name);
    }

    protected function indexCommand(string $type, string|array $columns, ?string $name = null): IndexDefinition
    {
        $columns = (array) $columns;
        $index = new IndexDefinition([
            'type' => $type,
            'columns' => $columns,
            'name' => $name ?: $this->createIndexName($type, $columns)
        ]);

        $this->addCommand($type, ['index' => $index]);
        return $index;
    }

    protected function createIndexName(string $type, array $columns): string
    {
        $name = strtolower($this->table . '_' . implode('_', $columns) . '_' . $type);
        return str_replace(['-', '.'], '_', $name);
    }

    // Drop methods
    public function dropColumn(string|array $columns): void
    {
        $columns = (array) $columns;
        $this->addCommand('dropColumn', compact('columns'));
    }

    public function dropPrimary(?string $index = null): void
    {
        $this->addCommand('dropPrimary', compact('index'));
    }

    public function dropUnique(string|array $index): void
    {
        $this->addCommand('dropUnique', compact('index'));
    }

    public function dropIndex(string|array $index): void
    {
        $this->addCommand('dropIndex', compact('index'));
    }

    public function dropFullText(string|array $index): void
    {
        $this->addCommand('dropFullText', compact('index'));
    }

    public function dropSpatialIndex(string|array $index): void
    {
        $this->addCommand('dropSpatialIndex', compact('index'));
    }

    public function dropForeign(string|array $index): void
    {
        $this->addCommand('dropForeign', compact('index'));
    }

    public function dropConstrainedForeignId(string $column): void
    {
        $this->dropForeign([$column]);
        $this->dropColumn($column);
    }

    public function dropTimestamps(): void
    {
        $this->dropColumn(['created_at', 'updated_at']);
    }

    public function dropTimestampsTz(): void
    {
        $this->dropColumn(['created_at', 'updated_at']);
    }

    public function dropSoftDeletes(): void
    {
        $this->dropColumn('deleted_at');
    }

    public function dropSoftDeletesTz(): void
    {
        $this->dropColumn('deleted_at');
    }

    // Laravel-style remember token
    public function rememberToken(): ColumnDefinition
    {
        return $this->string('remember_token', 100)->nullable();
    }

    // Polymorphic relationship columns
    public function morphs(string $name, ?string $indexName = null): void
    {
        $this->string($name . '_type');
        $this->unsignedBigInteger($name . '_id');
        $this->index([$name . '_type', $name . '_id'], $indexName);
    }

    public function nullableMorphs(string $name, ?string $indexName = null): void
    {
        $this->string($name . '_type')->nullable();
        $this->unsignedBigInteger($name . '_id')->nullable();
        $this->index([$name . '_type', $name . '_id'], $indexName);
    }

    public function uuidMorphs(string $name, ?string $indexName = null): void
    {
        $this->string($name . '_type');
        $this->uuid($name . '_id');
        $this->index([$name . '_type', $name . '_id'], $indexName);
    }

    public function nullableUuidMorphs(string $name, ?string $indexName = null): void
    {
        $this->string($name . '_type')->nullable();
        $this->uuid($name . '_id')->nullable();
        $this->index([$name . '_type', $name . '_id'], $indexName);
    }

    // Drop polymorphic columns
    public function dropMorphs(string $name, ?string $indexName = null): void
    {
        $indexName = $indexName ?: $this->createIndexName('index', [$name . '_type', $name . '_id']);
        $this->dropIndex($indexName);
        $this->dropColumn([$name . '_type', $name . '_id']);
    }

    // Table modification methods
    public function rename(string $to): void
    {
        $this->addCommand('rename', compact('to'));
    }

    public function drop(): void
    {
        $this->addCommand('drop');
    }

    public function dropIfExists(): void
    {
        $this->addCommand('dropIfExists');
    }

    // Table options
    public function engine(string $engine): self
    {
        $this->engine = $engine;
        return $this;
    }

    public function charset(string $charset): self
    {
        $this->charset = $charset;
        return $this;
    }

    public function collation(string $collation): self
    {
        $this->collation = $collation;
        return $this;
    }

    public function temporary(): self
    {
        $this->temporary = true;
        return $this;
    }

    public function comment(string $comment): self
    {
        $this->addCommand('comment', compact('comment'));
        return $this;
    }

    // Getters
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getCommands(): array
    {
        return $this->commands;
    }

    public function getEngine(): string
    {
        return $this->engine;
    }

    public function getCharset(): string
    {
        return $this->charset;
    }

    public function getCollation(): string
    {
        return $this->collation;
    }

    public function isTemporary(): bool
    {
        return $this->temporary;
    }

    /**
     * Get a column by name
     */
    public function getColumn(string $name): ?ColumnDefinition
    {
        return $this->columns[$name] ?? null;
    }

    /**
     * Check if column exists
     */
    public function hasColumn(string $name): bool
    {
        return isset($this->columns[$name]);
    }

    /**
     * Get all primary key columns
     */
    public function getPrimaryKeys(): array
    {
        $primaryKeys = [];
        foreach ($this->commands as $command) {
            if ($command['name'] === 'primary' && isset($command['index'])) {
                $primaryKeys = array_merge($primaryKeys, $command['index']->getColumns());
            }
        }
        return $primaryKeys;
    }
}
