<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateSuperAdmin extends Command
{
    protected $signature   = 'landlord:create-super-admin';
    protected $description = 'Crea un usuario super_admin para el panel landlord';

    public function handle(): int
    {
        $this->info('Crear super administrador del panel landlord');
        $this->newLine();

        $name     = $this->ask('Nombre');
        $email    = $this->ask('Email');
        $password = $this->secret('Contraseña (mínimo 8 caracteres)');

        if (strlen($password) < 8) {
            $this->error('La contraseña debe tener al menos 8 caracteres.');
            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->error("Ya existe un usuario con el email {$email}.");
            return self::FAILURE;
        }

        User::create([
            'tenant_id'     => null,
            'name'          => $name,
            'email'         => $email,
            'password'      => Hash::make($password),
            'is_super_admin'=> true,
        ]);

        $this->newLine();
        $this->info("Super admin \"{$name}\" creado correctamente.");
        $this->line("Accede en: /landlord/login");

        return self::SUCCESS;
    }
}
