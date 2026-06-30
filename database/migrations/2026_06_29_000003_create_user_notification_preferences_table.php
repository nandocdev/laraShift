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
        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id');
            $table->string('notification_key');
            $table->string('channel', 20)->default('email');
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
            $table->unique(['tenant_id', 'user_id', 'notification_key', 'channel'], 'user_notif_pref_unique');

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE user_notification_preferences ENABLE ROW LEVEL SECURITY;');
            DB::statement('ALTER TABLE user_notification_preferences FORCE ROW LEVEL SECURITY;');
            DB::statement("CREATE POLICY tenant_isolation ON user_notification_preferences USING (tenant_id::text = current_setting('app.tenant_id')) WITH CHECK (tenant_id::text = current_setting('app.tenant_id'));");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
    }
};
