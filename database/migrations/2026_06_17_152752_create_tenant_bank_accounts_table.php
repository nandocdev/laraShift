<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_bank_accounts', function (Blueprint $blueprint) {
            $blueprint->uuid('id')->primary();
            $blueprint->uuid('tenant_id');
            $blueprint->string('country', 2);
            $blueprint->string('currency', 3);
            $blueprint->string('method')->default('BANK_TRANSFER');
            $blueprint->json('beneficiary'); // Encrypted or sensitive account details
            $blueprint->boolean('is_active')->default(true);
            $blueprint->timestamps();
            $blueprint->softDeletes();

            $blueprint->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_bank_accounts');
    }
};
