<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Central\Settings\Support\CentralBranding;
use Illuminate\Database\Seeder;

class CentralBrandingSeeder extends Seeder
{
    public function run(): void
    {
        CentralBranding::set('platform_name', 'LaraShift SaaS');
        CentralBranding::set('primary_color', '#4f46e5'); // Indigo 600
        CentralBranding::set('logo_url', 'https://larashift.test/img/logo.png');
    }
}
