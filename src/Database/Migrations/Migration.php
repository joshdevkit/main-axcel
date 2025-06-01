<?php

namespace Axcel\Database\Migrations;

use Axcel\Database\Schema\Schema;

abstract class Migration
{
    /**
     * Run the migrations.
     */
    abstract public function up(): void;

    /**
     * Reverse the migrations.
     */
    abstract public function down(): void;

    /**
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        return null;
    }
}
