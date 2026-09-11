<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const VALUES_3 = "'all','plan','status'";

    private const VALUES_4 = "'all','plan','status','selected'";

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $this->replaceCheck('pgsql', self::VALUES_4);

            return;
        }

        // SQLite no soporta DROP CONSTRAINT: rebuild con el CHECK ampliado.
        // broadcast_tenant se recrea después (solo afecta a datos locales/test).
        Schema::dropIfExists('broadcast_tenant');
        $this->rebuild('sqlite', ['all', 'plan', 'status', 'selected']);
        $this->createPivot();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $this->replaceCheck('pgsql', self::VALUES_3);

            return;
        }

        Schema::dropIfExists('broadcast_tenant');
        $this->rebuild('sqlite', ['all', 'plan', 'status']);
        $this->createPivot();
    }

    private function replaceCheck(string $driver, string $values): void
    {
        if ($driver === 'pgsql') {
            $names = DB::select(
                "SELECT conname FROM pg_constraint WHERE conrelid = 'broadcasts'::regclass AND contype = 'c' AND pg_get_constraintdef(oid) LIKE '%filter_type%'"
            );

            foreach ($names as $row) {
                DB::statement('ALTER TABLE broadcasts DROP CONSTRAINT "'.$row->conname.'"');
            }

            DB::statement("ALTER TABLE broadcasts ADD CONSTRAINT broadcasts_filter_type_check CHECK (filter_type IN ({$values}))");
        }
    }

    /**
     * @param  list<string>  $allowed
     */
    private function rebuild(string $driver, array $allowed): void
    {
        Schema::create('broadcasts_new', function (Blueprint $table) use ($allowed) {
            $table->uuid('id')->primary();
            $table->foreignUuid('created_by')->constrained('central_users')->onDelete('cascade');
            $table->string('title');
            $table->text('body');
            $table->enum('filter_type', $allowed);
            $table->string('filter_value')->nullable();
            $table->json('channels');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->boolean('is_draft')->default(false);
            $table->integer('recipient_count')->nullable();
            $table->timestamps();
        });

        $columns = ['id', 'created_by', 'title', 'body', 'filter_type', 'filter_value', 'channels', 'sent_at', 'scheduled_at', 'is_draft', 'recipient_count', 'created_at', 'updated_at'];

        DB::table('broadcasts_new')->insertUsing(
            $columns,
            DB::table('broadcasts')->select($columns)
        );

        Schema::drop('broadcasts');
        Schema::rename('broadcasts_new', 'broadcasts');
    }

    private function createPivot(): void
    {
        Schema::create('broadcast_tenant', function (Blueprint $table) {
            $table->foreignUuid('broadcast_id')->constrained('broadcasts')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->primary(['broadcast_id', 'tenant_id']);
        });
    }
};
