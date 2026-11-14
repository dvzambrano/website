<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Crea la tabla de roles y registra los roles base.
     * Solo inserta si no existen (insertOrIgnore): es seguro re-ejecutarla.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('slug', 50)->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('roles')->insertOrIgnore([
            ['name' => 'Administrador', 'slug' => 'admin', 'description' => 'Acceso al visor de logs y a la administración.', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Usuario', 'slug' => 'user', 'description' => 'Usuario estándar.', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Elimina la tabla. Solo válido si antes se retiró la FK de users (migración siguiente).
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
