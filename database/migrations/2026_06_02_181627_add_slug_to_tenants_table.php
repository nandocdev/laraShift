<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('id');
        });

        // Migrate data from JSON if exists
        DB::table('tenants')->get()->each(function ($tenant) {
            $data = json_decode($tenant->data, true);
            if (isset($data['slug'])) {
                DB::table('tenants')->where('id', $tenant->id)->update(['slug' => $data['slug']]);
            } else {
                // Use ID as fallback slug if missing
                DB::table('tenants')->where('id', $tenant->id)->update(['slug' => $tenant->id]);
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
