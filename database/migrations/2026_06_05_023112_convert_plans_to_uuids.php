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
        // 1. Drop foreign keys that point to plans.id
        Schema::table('plan_features', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
        });

        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropForeign(['plan_id']);
            });
        }

        // 2. Change plans.id to UUID
        // In Postgres, we need to drop the primary key and change type
        DB::statement('ALTER TABLE plans DROP CONSTRAINT plans_pkey CASCADE');
        DB::statement('ALTER TABLE plans ALTER COLUMN id DROP DEFAULT');
        DB::statement('ALTER TABLE plans ALTER COLUMN id TYPE UUID USING (gen_random_uuid())');
        DB::statement('ALTER TABLE plans ADD PRIMARY KEY (id)');

        // 3. Change plan_id in dependent tables to UUID
        DB::statement('ALTER TABLE plan_features ALTER COLUMN plan_id TYPE UUID USING (NULL)');
        if (Schema::hasTable('subscriptions')) {
            DB::statement('ALTER TABLE subscriptions ALTER COLUMN plan_id TYPE UUID USING (NULL)');
        }

        // 4. Make package-specific columns nullable
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->nullable()->change();
            $table->integer('interval_count')->nullable()->change();
            $table->string('provider_plan_id')->nullable()->change();
            $table->string('currency')->nullable()->change();
            $table->string('interval')->nullable()->change();
        });

        // 5. Re-add foreign keys
        Schema::table('plan_features', function (Blueprint $table) {
            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('cascade');
        });

        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->foreign('plan_id')->references('id')->on('plans')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverting this is complex and probably not needed in this dev environment fix
    }
};
