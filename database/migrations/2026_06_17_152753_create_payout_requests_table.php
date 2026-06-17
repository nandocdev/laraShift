<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_requests', function (Blueprint $blueprint) {
            $blueprint->uuid('id')->primary();
            $blueprint->uuid('tenant_id');
            $blueprint->uuid('bank_account_id');
            $blueprint->decimal('amount', 15, 2);
            $blueprint->string('currency', 3);
            $blueprint->string('status')->default('pending'); // pending, processing, paid, rejected
            $blueprint->string('gateway_reference')->nullable();
            $blueprint->string('error_message')->nullable();
            $blueprint->json('metadata')->nullable();
            $blueprint->timestamps();
            $blueprint->softDeletes();

            $blueprint->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $blueprint->foreign('bank_account_id')->references('id')->on('tenant_bank_accounts');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_requests');
    }
};
