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
        // 1. Update central_users
        Schema::table('central_users', function (Blueprint $table) {
            $table->boolean('is_global_admin')->default(false)->after('email');
            $table->timestamp('locked_until')->nullable()->after('password');
        });

        // 2. Create central_2fa
        Schema::create('central_2fa', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('central_users')->onDelete('cascade')->unique();
            $table->string('method')->default('totp'); // totp, webauthn
            $table->text('secret'); // Encrypted
            $table->text('recovery_codes'); // Encrypted JSON
            $table->timestamp('enrolled_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('central_2fa');
        Schema::table('central_users', function (Blueprint $table) {
            $table->dropColumn(['is_global_admin', 'locked_until']);
        });
    }
};
