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
        Schema::create('user_mfa', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('user_id')->unique();
            $table->string('method')->default('totp');
            $table->text('secret'); // Encrypted
            $table->text('recovery_codes'); // Encrypted JSON
            $table->timestamp('enrolled_at');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Enable RLS
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'pgsql') {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE user_mfa ENABLE ROW LEVEL SECURITY;");
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE user_mfa FORCE ROW LEVEL SECURITY;");
            \Illuminate\Support\Facades\DB::statement("CREATE POLICY tenant_isolation ON user_mfa USING (tenant_id::text = current_setting('app.tenant_id')) WITH CHECK (tenant_id::text = current_setting('app.tenant_id'));");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_mfa');
    }
};
