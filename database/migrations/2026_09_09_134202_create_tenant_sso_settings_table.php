<?php

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
        Schema::create('tenant_sso_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('idp_entity_id')->nullable();
            $table->string('idp_sso_url')->nullable();
            $table->text('idp_x509_cert')->nullable();
            $table->json('enforced_domains')->nullable();
            $table->boolean('is_forced')->default(false);
            $table->boolean('is_tested')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_sso_settings');
    }
};
