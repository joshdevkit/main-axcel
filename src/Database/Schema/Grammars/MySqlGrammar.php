<?php

namespace Axcel\Database\Schema\Grammars;

use Axcel\Database\Schema\Blueprint;
use Axcel\Database\Schema\Definitions\ColumnDefinition;

class MySqlGrammar
{
    /**
     * Compile a create table command
     */
    public function compileCreate(Blueprint $blueprint): string
    {
        $columns = $this->getColumns($blueprint);
        $foreignKeys = $this->getForeignKeys($blueprint);

        // Combine columns and foreign keys
        $definitions = array_merge($columns, $foreignKeys);

        return sprintf(
            'CREATE TABLE `%s` (%s) ENGINE=%s DEFAULT CHARSET=%s COLLATE=%s',
            $blueprint->getTable(),
            implode(', ', $definitions),
            $blueprint->getEngine(),
            $blueprint->getCharset(),
            $blueprint->getCollation()
        );
    }

    /**
     * Get column definitions
     */
    protected function getColumns(Blueprint $blueprint): array
    {
        $columns = [];

        foreach ($blueprint->getColumns() as $column) {
            $columns[] = $this->getColumnDefinition($column);
        }

        return $columns;
    }

    /**
     * Get foreign key definitions
     */
    protected function getForeignKeys(Blueprint $blueprint): array
    {
        $foreignKeys = [];

        foreach ($blueprint->getForeignKeys() as $foreignKey) {
            $foreignKeys[] = sprintf(
                'CONSTRAINT `%s_%s_foreign` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`) ON DELETE CASCADE ON UPDATE CASCADE',
                $blueprint->getTable(),
                $foreignKey->getAttribute('local_column'),
                $foreignKey->getAttribute('local_column'),
                $foreignKey->getAttribute('foreign_table'),
                $foreignKey->getAttribute('foreign_column')
            );
        }

        return $foreignKeys;
    }

    /**
     * Get the column definition
     */
    protected function getColumnDefinition(ColumnDefinition $column): string
    {
        $segments = [
            "`{$column->getName()}`",
            $this->getColumnType($column),
        ];

        // Special handling for timestamp columns
        if ($column->getType() === 'timestamp') {
            if ($column->isNullable()) {
                $segments[] = 'NULL';
            } else {
                $segments[] = 'NOT NULL';
            }

            // Handle default value for timestamps
            if ($column->hasDefault()) {
                $segments[] = $this->getDefaultValue($column);
            } else if ($column->isNullable()) {
                $segments[] = 'DEFAULT NULL';
            }

            return implode(' ', $segments);
        }

        // Regular columns
        if (!$column->isNullable()) {
            $segments[] = 'NOT NULL';
        }

        if ($column->hasDefault()) {
            $segments[] = $this->getDefaultValue($column);
        }

        if ($column->isAutoIncrement()) {
            $segments[] = 'AUTO_INCREMENT';
        }

        if ($column->isPrimary()) {
            $segments[] = 'PRIMARY KEY';
        }

        return implode(' ', $segments);
    }

    protected function getDefaultValue(ColumnDefinition $column): string
    {
        $default = $column->getAttribute('default');

        if ($column->getType() === 'timestamp') {
            if ($default === 'CURRENT_TIMESTAMP') {
                return 'DEFAULT CURRENT_TIMESTAMP';
            }
            if ($default === null) {
                return 'DEFAULT NULL';
            }
        }

        if (is_bool($default)) {
            return 'DEFAULT ' . ($default ? '1' : '0');
        }

        if (is_null($default)) {
            return 'DEFAULT NULL';
        }

        if (is_numeric($default)) {
            return 'DEFAULT ' . $default;
        }

        return "DEFAULT '" . addslashes($default) . "'";
    }

    protected function getColumnType(ColumnDefinition $column): string
    {
        $type = $column->getType();

        switch ($type) {
            case 'bigInteger':
                return $column->isUnsigned() ? 'BIGINT UNSIGNED' : 'BIGINT';
            case 'integer':
                return $column->isUnsigned() ? 'INT UNSIGNED' : 'INT';
            case 'string':
                $length = $column->getAttribute('length', 255);
                return "VARCHAR($length)";
            case 'text':
                return 'TEXT';
            case 'boolean':
                return 'TINYINT(1)';
            case 'timestamp':
                return 'TIMESTAMP';
            default:
                return strtoupper($type);
        }
    }

    /**
     * Compile a drop table command
     */
    public function compileDrop(Blueprint $blueprint): string
    {
        return "DROP TABLE `{$blueprint->getTable()}`";
    }

    /**
     * Compile alter table commands
     */
    public function compileAlter(Blueprint $blueprint): array
    {
        $statements = [];

        foreach ($blueprint->getCommands() as $command) {
            if (method_exists($this, 'compile' . ucfirst($command['name']))) {
                $method = 'compile' . ucfirst($command['name']);
                $statements[] = $this->$method($blueprint, $command);
            }
        }

        return $statements;
    }
}
