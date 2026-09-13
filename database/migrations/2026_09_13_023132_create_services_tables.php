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
        Schema::create('service_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('color', 7)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('category_id')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->integer('duration_minutes');
            $table->integer('buffer_before_minutes')->default(0);
            $table->integer('buffer_after_minutes')->default(0);
            $table->integer('capacity')->default(1);
            $table->integer('price_cents')->default(0);
            $table->string('currency', 3)->nullable();
            $table->string('deposit_type', 20)->default('none');
            $table->integer('deposit_value')->nullable();
            $table->integer('cancellation_window_hours')->nullable();
            $table->json('cancellation_policy')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('category_id');

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('service_categories')->onDelete('set null');
        });

        Schema::create('location_service', function (Blueprint $table) {
            $table->uuid('location_id');
            $table->uuid('service_id');
            $table->uuid('tenant_id');

            $table->primary(['location_id', 'service_id']);
            $table->index('tenant_id');

            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // Slugs stay unique per tenant only among live rows: soft-deleted
        // records free their slug for reuse. Partial unique indexes are
        // supported by both PostgreSQL and SQLite.
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX service_categories_tenant_slug_unique ON service_categories (tenant_id, slug) WHERE deleted_at IS NULL');
            DB::statement('CREATE UNIQUE INDEX services_tenant_slug_unique ON services (tenant_id, slug) WHERE deleted_at IS NULL');
        }

        if (DB::getDriverName() === 'pgsql') {
            foreach (['service_categories', 'services', 'location_service'] as $table) {
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
        Schema::dropIfExists('location_service');
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_categories');
    }
};
