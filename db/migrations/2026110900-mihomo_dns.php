<?php

declare(strict_types=1);
use App\Interfaces\MigrationInterface;
use App\Services\DB;
return new class() implements MigrationInterface {
    public function up(): int
    {
        DB::getPdo()->exec("ALTER TABLE config MODIFY COLUMN value MEDIUMTEXT NOT NULL");
        return 2026110900;
    }
    public function down(): int
    {
        // Keep the widened column: narrowing could truncate saved operator settings.
        return 2025073100;
    }
};
