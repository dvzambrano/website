<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

class SetUserRole extends Command
{
    protected $signature = 'users:set-role {email : Correo del usuario} {role? : Slug del rol (admin, user); omitir para quitarle el rol}';

    protected $description = 'Asigna o quita el rol de un usuario (sin rol = no administrador)';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (!$user) {
            $this->error('No existe un usuario con ese correo.');
            return self::FAILURE;
        }

        $slug = $this->argument('role');
        if ($slug === null) {
            $user->role()->dissociate();
        } else {
            $role = Role::where('slug', $slug)->first();
            if (!$role) {
                $this->error("No existe el rol '{$slug}'.");
                return self::FAILURE;
            }
            $user->role()->associate($role);
        }

        $user->save();
        $this->info("Rol de {$user->email}: " . ($user->role?->slug ?? 'sin rol'));
        return self::SUCCESS;
    }
}
