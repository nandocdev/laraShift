<?php

declare(strict_types=1);

namespace App\Modules\Shared\Tenancy\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EnableRlsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenancy:enable-rls {table}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enable Row Level Security on a specific table for PostgreSQL';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $table = $this->argument('table');

        if (! config('database.default') === 'pgsql') {
            $this->error('Row Level Security is only supported on PostgreSQL.');
            return self::FAILURE;
        }

        try {
            $this->info("Enabling RLS on table: {$table}");

            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");

            $policyName = "tenant_isolation_{$table}";
            
            // Drop policy if exists to avoid errors on re-run
            DB::statement("DROP POLICY IF EXISTS {$policyName} ON {$table}");

            // Create policy
            DB::statement("
                CREATE POLICY {$policyName} ON {$table}
                USING (tenant_id = current_setting('app.tenant_id')::uuid)
                WITH CHECK (tenant_id = current_setting('app.tenant_id')::uuid)
            ");

            $this->info("RLS enabled and policy '{$policyName}' created successfully.");

        } catch (\Exception $e) {
            $this->error("Failed to enable RLS: {$e->getMessage()}");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
