<?php

declare(strict_types=1);

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
        // 1. Drop existing foreign keys
        $this->dropForeignIfExists('subscription_items', 'subscription_items_subscription_id_foreign');
        $this->dropForeignIfExists('invoices', 'invoices_tenant_id_foreign');
        $this->dropForeignIfExists('invoices', 'invoices_subscription_id_foreign');
        $this->dropForeignIfExists('subscriptions', 'subscriptions_tenant_id_foreign');

        // 2. Convert subscriptions table
        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_pkey CASCADE');
        DB::statement('ALTER TABLE subscriptions ALTER COLUMN id DROP DEFAULT');
        DB::statement('ALTER TABLE subscriptions ALTER COLUMN id TYPE UUID USING (gen_random_uuid())');
        DB::statement('ALTER TABLE subscriptions ALTER COLUMN tenant_id TYPE UUID USING (NULL)');
        DB::statement('ALTER TABLE subscriptions ADD PRIMARY KEY (id)');

        // 3. Convert subscription_items table
        DB::statement('ALTER TABLE subscription_items DROP CONSTRAINT IF EXISTS subscription_items_pkey CASCADE');
        DB::statement('ALTER TABLE subscription_items ALTER COLUMN id DROP DEFAULT');
        DB::statement('ALTER TABLE subscription_items ALTER COLUMN id TYPE UUID USING (gen_random_uuid())');
        DB::statement('ALTER TABLE subscription_items ALTER COLUMN subscription_id TYPE UUID USING (NULL)');
        DB::statement('ALTER TABLE subscription_items ADD PRIMARY KEY (id)');

        // 4. Convert invoices table
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_pkey CASCADE');
        DB::statement('ALTER TABLE invoices ALTER COLUMN id DROP DEFAULT');
        DB::statement('ALTER TABLE invoices ALTER COLUMN id TYPE UUID USING (gen_random_uuid())');
        DB::statement('ALTER TABLE invoices ALTER COLUMN tenant_id TYPE UUID USING (NULL)');
        DB::statement('ALTER TABLE invoices ALTER COLUMN subscription_id TYPE UUID USING (NULL)');
        DB::statement('ALTER TABLE invoices ADD PRIMARY KEY (id)');

        // 5. Re-add foreign keys
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::table('subscription_items', function (Blueprint $table) {
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('cascade');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('set null');
        });
    }

    private function dropForeignIfExists(string $table, string $foreign): void
    {
        if (Schema::hasTable($table)) {
            $exists = DB::select("SELECT 1 FROM pg_constraint WHERE conname = ?", [$foreign]);
            if (!empty($exists)) {
                Schema::table($table, function (Blueprint $table) use ($foreign) {
                    $table->dropForeign($foreign);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 
    }
};
