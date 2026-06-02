<?php

namespace App\Console\Commands;

use App\Modules\Central\Auth\Models\CentralUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateCentralUserCommand extends Command
{
    protected $signature = 'central:create-user {name} {email} {password}';

    protected $description = 'Crea un nuevo usuario administrador central';

    public function handle()
    {
        $user = CentralUser::create([
            'name' => $this->argument('name'),
            'email' => $this->argument('email'),
            'password' => Hash::make($this->argument('password')),
        ]);

        $this->info("Usuario central creado: {$user->email}");
    }
}
