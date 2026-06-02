<?php

namespace App\Console\Commands;

use App\Modules\Central\Provisioning\Actions\CreateTenantAction;
use App\Modules\Central\Provisioning\DTOs\CreateTenantData;
use Illuminate\Console\Command;

class ProvisionTenantCommand extends Command
{
    protected $signature = 'provision:tenant {name} {slug} {email}';

    protected $description = 'Provisions a new tenant atomically';

    public function handle(CreateTenantAction $action)
    {
        $data = new CreateTenantData(
            name: $this->argument('name'),
            slug: $this->argument('slug'),
            email: $this->argument('email'),
        );

        $this->info("Provisioning tenant: {$data->name} ({$data->slug})...");

        try {
            $tenant = $action->execute($data);
            $this->info("Tenant created successfully: {$tenant->id}");
            $this->info("Domain: " . $tenant->domains()->first()->domain);
        } catch (\Exception $e) {
            $this->error("Failed to provision tenant: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
