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
        Schema::create('tenant_notification_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('key');
            $table->string('channel', 20)->default('email');
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'key']);
            $table->index(['tenant_id', 'channel']);
            $table->unique(['tenant_id', 'key', 'channel']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tenant_notification_templates ENABLE ROW LEVEL SECURITY;');
            DB::statement('ALTER TABLE tenant_notification_templates FORCE ROW LEVEL SECURITY;');
            DB::statement("CREATE POLICY tenant_isolation ON tenant_notification_templates USING (tenant_id::text = current_setting('app.tenant_id')) WITH CHECK (tenant_id::text = current_setting('app.tenant_id'));");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_notification_templates');
    }
};
