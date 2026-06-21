<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Global features catalog
        Schema::create('features', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('module')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. M:N Plan <-> Features
        Schema::create('plan_features', function (Blueprint $table) {
            $table->foreignUuid('plan_id')->constrained('plans')->onDelete('cascade');
            $table->foreignUuid('feature_id')->constrained('features')->onDelete('cascade');

            $table->primary(['plan_id', 'feature_id']);
        });

        // 3. Tenant-specific Overrides
        Schema::create('tenant_feature_overrides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('feature_id')->constrained('features')->onDelete('cascade');
            $table->enum('type', ['allow', 'deny']);
            $table->text('reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('central_users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['tenant_id', 'feature_id']);
        });

        // Enable RLS
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'pgsql') {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE tenant_feature_overrides ENABLE ROW LEVEL SECURITY;");
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE tenant_feature_overrides FORCE ROW LEVEL SECURITY;");
            \Illuminate\Support\Facades\DB::statement("CREATE POLICY tenant_isolation ON tenant_feature_overrides USING (tenant_id::text = current_setting('app.tenant_id')) WITH CHECK (tenant_id::text = current_setting('app.tenant_id'));");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_feature_overrides');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('features');
    }
};
