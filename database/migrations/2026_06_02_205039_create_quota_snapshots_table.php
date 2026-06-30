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
        Schema::create('quota_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('metric');
            $table->integer('usage');
            $table->integer('limit');
            $table->string('period'); // e.g., '2026-06'
            $table->timestamps();

            $table->index(['tenant_id', 'metric', 'period']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE quota_snapshots ENABLE ROW LEVEL SECURITY;');
            DB::statement('ALTER TABLE quota_snapshots FORCE ROW LEVEL SECURITY;');
            DB::statement("CREATE POLICY tenant_isolation ON quota_snapshots USING (tenant_id::text = current_setting('app.tenant_id')) WITH CHECK (tenant_id::text = current_setting('app.tenant_id'));");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quota_snapshots');
    }
};
