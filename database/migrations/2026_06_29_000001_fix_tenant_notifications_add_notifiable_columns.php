<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_notifications', function (Blueprint $table) {
            $table->uuid('notifiable_id')->nullable()->after('tenant_id');
            $table->string('notifiable_type')->nullable()->after('notifiable_id');

            $table->index(['notifiable_id', 'notifiable_type']);
        });
    }

    public function down(): void
    {
        Schema::table('tenant_notifications', function (Blueprint $table) {
            $table->dropIndex(['notifiable_id', 'notifiable_type']);
            $table->dropColumn(['notifiable_id', 'notifiable_type']);
        });
    }
};
