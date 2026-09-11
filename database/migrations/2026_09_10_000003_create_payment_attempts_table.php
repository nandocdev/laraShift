<?php

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
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('slug');
            $table->string('status');
            $table->jsonb('payload')->default('{}');
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payment_attempts ENABLE ROW LEVEL SECURITY;');
            DB::statement('ALTER TABLE payment_attempts FORCE ROW LEVEL SECURITY;');
            DB::statement("CREATE POLICY tenant_isolation ON payment_attempts USING (tenant_id::text = current_setting('app.tenant_id')) WITH CHECK (tenant_id::text = current_setting('app.tenant_id'));");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
