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
        Schema::create('broadcast_dismissals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('broadcast_id')->constrained('broadcasts')->onDelete('cascade');
            $table->uuid('tenant_id')->index();
            $table->uuid('user_id')->index(); // Specific user who dismissed
            $table->timestamp('dismissed_at');
            $table->timestamps();

            $table->unique(['broadcast_id', 'user_id']);
        });

        // Enable RLS
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'pgsql') {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE broadcast_dismissals ENABLE ROW LEVEL SECURITY;");
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE broadcast_dismissals FORCE ROW LEVEL SECURITY;");
            \Illuminate\Support\Facades\DB::statement("CREATE POLICY tenant_isolation ON broadcast_dismissals USING (tenant_id::text = current_setting('app.tenant_id')) WITH CHECK (tenant_id::text = current_setting('app.tenant_id'));");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('broadcast_dismissals');
    }
};
