<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE INDEX IF NOT EXISTS broadcasts_channels_gin ON broadcasts USING GIN (channels)');
        DB::statement('CREATE INDEX IF NOT EXISTS broadcasts_filter_type_value_idx ON broadcasts (filter_type, filter_value)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS broadcasts_channels_gin');
        DB::statement('DROP INDEX IF EXISTS broadcasts_filter_type_value_idx');
    }
};
