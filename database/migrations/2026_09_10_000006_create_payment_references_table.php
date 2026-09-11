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
        Schema::create('payment_references', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('external_reference')->unique();
            $table->string('order_id')->nullable()->index();
            $table->string('context');
            $table->nullableMorphs('owner');
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payment_references ENABLE ROW LEVEL SECURITY;');
            DB::statement('ALTER TABLE payment_references FORCE ROW LEVEL SECURITY;');
            DB::statement("CREATE POLICY tenant_isolation ON payment_references USING (tenant_id::text = current_setting('app.tenant_id')) WITH CHECK (tenant_id::text = current_setting('app.tenant_id'));");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_references');
    }
};
