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
        Schema::create('resources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('location_id')->nullable();
            $table->string('type', 20)->default('staff');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->integer('capacity')->default(1);
            $table->json('skills')->nullable();
            $table->integer('rate_cents')->nullable();
            $table->string('currency', 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('location_id');

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('set null');
        });

        Schema::create('resource_service', function (Blueprint $table) {
            $table->uuid('resource_id');
            $table->uuid('service_id');
            $table->uuid('tenant_id');

            $table->primary(['resource_id', 'service_id']);
            $table->index('tenant_id');

            $table->foreign('resource_id')->references('id')->on('resources')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // Slugs stay unique per tenant only among live rows: soft-deleted
        // records free their slug for reuse. Partial unique indexes are
        // supported by both PostgreSQL and SQLite.
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX resources_tenant_slug_unique ON resources (tenant_id, slug) WHERE deleted_at IS NULL');
        }

        if (DB::getDriverName() === 'pgsql') {
            foreach (['resources', 'resource_service'] as $table) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY;");
                DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY;");
                DB::statement("CREATE POLICY tenant_isolation ON {$table} USING (tenant_id::text = current_setting('app.tenant_id'));");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_service');
        Schema::dropIfExists('resources');
    }
};
