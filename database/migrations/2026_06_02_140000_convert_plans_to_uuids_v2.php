<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // This migration fixes the incompatible types between plans.id (bigint) 
        // and plan_features.plan_id (uuid) which causes migration 2026_06_02_143356 to fail.

        // 1. Drop foreign keys that point to plans.id in the billing package tables if they exist
        $this->dropForeignIfExists('subscriptions', 'subscriptions_plan_id_foreign');
        $this->dropForeignIfExists('plan_features', 'plan_features_plan_id_foreign');

        // 2. Change plans.id to UUID in Postgres
        DB::statement('ALTER TABLE plans DROP CONSTRAINT IF EXISTS plans_pkey CASCADE');
        DB::statement('ALTER TABLE plans ALTER COLUMN id DROP DEFAULT');
        DB::statement('ALTER TABLE plans ALTER COLUMN id TYPE UUID USING (gen_random_uuid())');
        DB::statement('ALTER TABLE plans ADD PRIMARY KEY (id)');

        // 3. Change plan_id in dependent tables to UUID
        if (Schema::hasTable('subscriptions')) {
            DB::statement('ALTER TABLE subscriptions ALTER COLUMN plan_id TYPE UUID USING (NULL)');
        }
        
        // Note: plan_features will be created by the failing migration, 
        // but we ensure compatibility by making sure the parent is already UUID.
    }

    private function dropForeignIfExists(string $table, string $foreign): void
    {
        if (Schema::hasTable($table)) {
            $exists = DB::select("SELECT 1 FROM pg_constraint WHERE conname = ?", [$foreign]);
            if (!empty($exists)) {
                Schema::table($table, function (Blueprint $table) use ($foreign) {
                    $table->dropForeign($foreign);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 
    }
};
