<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_data_backups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('file_path');
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tenant_data_backups ENABLE ROW LEVEL SECURITY;');
            DB::statement('ALTER TABLE tenant_data_backups FORCE ROW LEVEL SECURITY;');
            DB::statement("CREATE POLICY tenant_isolation ON tenant_data_backups USING (tenant_id::text = current_setting('app.tenant_id')) WITH CHECK (tenant_id::text = current_setting('app.tenant_id'));");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_data_backups');
    }
};
