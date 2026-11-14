<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Agrega role_id nullable: los usuarios existentes quedan sin rol
     * (null) y no se modifica ninguna fila. Borrar un rol deja a sus
     * usuarios sin rol (nullOnDelete) en vez de borrarlos.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role_id')) {
                $table->foreignId('role_id')
                    ->nullable()
                    ->after('google2fa_enabled')
                    ->constrained('roles')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Quita la FK y la columna; la tabla roles la borra la migración anterior.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'role_id')) {
                $table->dropConstrainedForeignId('role_id');
            }
        });
    }
};
