<?php

namespace Database\Seeders;

use App\Modules\Central\Auth\Models\CentralUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CentralUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = 'truck-grape-early@duck.com';

        CentralUser::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'LaraShift Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $this->command->info("Usuario Central Admin creado: {$email} / password");
    }
}
