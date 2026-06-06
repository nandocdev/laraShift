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
        Schema::table('plans', function (Blueprint $table) {
            if (! Schema::hasColumn('plans', 'slug')) {
                $table->string('slug')->unique()->after('name')->nullable();
            }
            if (! Schema::hasColumn('plans', 'price_monthly')) {
                $table->integer('price_monthly')->default(0)->after('slug');
            }
            if (! Schema::hasColumn('plans', 'price_yearly')) {
                $table->integer('price_yearly')->default(0)->after('price_monthly');
            }
            if (! Schema::hasColumn('plans', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('price_yearly');
            }
            if (! Schema::hasColumn('plans', 'features')) {
                $table->jsonb('features')->nullable()->after('is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['slug', 'price_monthly', 'price_yearly', 'is_active', 'features']);
        });
    }
};
