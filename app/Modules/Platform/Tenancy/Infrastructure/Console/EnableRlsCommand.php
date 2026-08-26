<?php

declare(strict_types=1);

namespace App\Modules\Platform\Tenancy\Infrastructure\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnableRlsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenancy:enable-rls {table? : Table to protect} {--all : Protect every table with a tenant_id column}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enable Row Level Security on a specific table (or all tenant-aware tables) for PostgreSQL';

    /**
     * Execute the command.
     */
    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->error('Row Level Security is only supported on PostgreSQL.');

            return self::FAILURE;
        }

        $tables = [];

        if ($this->option('all')) {
            $tables = $this->tenantAwareTables();

            if ($tables === []) {
                $this->info('No tenant-aware tables found.');

                return self::SUCCESS;
            }
        } else {
            $table = $this->argument('table');

            if (! is_string($table) || $table === '') {
                $this->error('Provide a table name or use the --all flag.');

                return self::FAILURE;
            }

            $tables = [$table];
        }

        $failures = 0;

        foreach ($tables as $table) {
            if (! $this->enableRlsOn((string) $table)) {
                $failures++;
            }
        }

        if ($failures > 0) {
            $this->error("Failed to enable RLS on {$failures} table(s).");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function tenantAwareTables(): array
    {
        return array_map(
            'strval',
            array_column(
                DB::select(
                    "SELECT c.relname AS table_name
                     FROM pg_class c
                     JOIN pg_namespace n ON n.oid = c.relnamespace
                     JOIN pg_attribute a ON a.attrelid = c.oid AND a.attname = 'tenant_id'
                     WHERE n.nspname = 'public' AND c.relkind = 'r'"
                ),
                'table_name'
            )
        );
    }

    private function enableRlsOn(string $table): bool
    {
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
                USING (tenant_id::text = current_setting('app.tenant_id'))
                WITH CHECK (tenant_id::text = current_setting('app.tenant_id'))
            ");

            $this->info("RLS enabled and policy '{$policyName}' created successfully.");
        } catch (\Throwable $e) {
            $this->error("Failed to enable RLS on {$table}: {$e->getMessage()}");

            return false;
        }

        return true;
    }
}
