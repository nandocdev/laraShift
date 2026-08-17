<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_rollups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('meter', 100);
            $table->string('period', 7); // 'Y-m'
            $table->integer('value')->default(0);
            $table->string('aggregation', 10)->default('sum');
            $table->timestamp('billed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'meter', 'period']);
            $table->index(['tenant_id', 'period']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE usage_rollups ENABLE ROW LEVEL SECURITY;');
            DB::statement('ALTER TABLE usage_rollups FORCE ROW LEVEL SECURITY;');
            DB::statement("CREATE POLICY tenant_isolation ON usage_rollups USING (tenant_id::text = current_setting('app.tenant_id')) WITH CHECK (tenant_id::text = current_setting('app.tenant_id'));");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_rollups');
    }
};
