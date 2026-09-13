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
        Schema::create('locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('slug');
            $table->json('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('timezone')->nullable();
            $table->json('settings')->default('{}');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // Slugs stay unique per tenant only among live rows: soft-deleted
        // locations free their slug for reuse. Partial unique indexes are
        // supported by both PostgreSQL and SQLite.
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX locations_tenant_slug_unique ON locations (tenant_id, slug) WHERE deleted_at IS NULL');
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE locations ENABLE ROW LEVEL SECURITY;');
            DB::statement('ALTER TABLE locations FORCE ROW LEVEL SECURITY;');
            DB::statement("CREATE POLICY tenant_isolation ON locations USING (tenant_id::text = current_setting('app.tenant_id'));");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
