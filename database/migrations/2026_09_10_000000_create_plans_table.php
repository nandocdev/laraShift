<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Filename sorts before the billing tables (000001+) so the
     * subscriptions.plan_id FK always resolves on fresh migrates.
     * PlanManager, features resolution and seeding land in Fase 1b.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('name');
            $table->integer('price_monthly');
            $table->integer('price_yearly');
            $table->char('currency', 3)->default('USD');
            $table->string('interval')->default('month');
            $table->jsonb('features')->default('{}');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE plans ENABLE ROW LEVEL SECURITY;');
            DB::statement('ALTER TABLE plans FORCE ROW LEVEL SECURITY;');
            DB::statement('CREATE POLICY tenant_isolation ON plans USING (true) WITH CHECK (true);');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
