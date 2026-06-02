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
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index();
            $table->unsignedBigInteger('subscription_id')->nullable()->index();
            $table->string('external_id')->nullable()->index(); // Stripe/dLocal invoice ID
            $table->string('number')->unique();
            $table->string('status'); // draft, open, paid, uncollectible, void
            $table->integer('amount_due');
            $table->integer('amount_paid');
            $table->string('currency', 3);
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->text('pdf_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
