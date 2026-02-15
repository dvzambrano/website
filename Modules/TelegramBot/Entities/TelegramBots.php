<?php

namespace Modules\TelegramBot\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Laravel\Traits\ModuleTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class TelegramBots extends Model
{
    use ModuleTrait;

    protected $table = "bots";

    protected $fillable = ['name', 'key', 'module', 'database', 'username', 'password', 'token', 'data'];

    protected $casts = [
        'data' => 'json',
        'password' => 'encrypted',
        'token' => 'encrypted',
    ];

    public $timestamps = true;

    protected $appends = ['code'];
    protected function code(): Attribute
    {
        return Attribute::make(
            get: function () {
                return str_replace("@", "", $this->name);
            },
        );
    }

    protected static function booted()
    {
        static::creating(function ($bot) {
            // Genera el UUID para la URL
            $bot->key = (string) Str::uuid();

            // Genera un token aleatorio de 32 caracteres para el Header
            $bot->secret = Str::random(32);
        });
    }

    public function connectToThisTenant()
    {
        // Configurar la conexión 'tenant' con los datos de este bot
        config([
            'database.connections.tenant' => [
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'database' => $this->database,
                'username' => $this->username ?: env('DB_USERNAME'),
                'password' => $this->password ?: env('DB_PASSWORD'),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]
        ]);

        // Limpiamos el caché de conexiones para que reconozca la nueva configuración
        DB::purge('tenant');
        DB::reconnect('tenant');

        // Opcional: Compartir la config con el resto de la app
        app()->instance('active_bot', $this);
    }
}
