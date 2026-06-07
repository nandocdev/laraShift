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
        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->unique();
            $table->string('name')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('primary_color', 7)->nullable();
            $table->string('timezone')->default('America/Panama');
            $table->string('locale')->default('es');
            $table->string('currency', 3)->default('USD');
            $table->boolean('mfa_required')->default(false);
            $table->string('smtp_host')->nullable();
            $table->integer('smtp_port')->nullable();
            $table->string('smtp_user')->nullable();
            $table->text('smtp_password')->nullable(); // Encrypted
            $table->string('smtp_from_email')->nullable();
            $table->string('smtp_from_name')->nullable();
            $table->boolean('smtp_verified')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        if (\Illuminate\Support\Facades\DB::getDriverName() === 'pgsql') {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE tenant_settings ENABLE ROW LEVEL SECURITY;");
            \Illuminate\Support\Facades\DB::statement("CREATE POLICY tenant_isolation ON tenant_settings USING (tenant_id = current_setting('app.tenant_id')::uuid);");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
    }
};
