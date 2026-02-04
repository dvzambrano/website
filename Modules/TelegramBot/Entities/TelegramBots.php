<?php

namespace Modules\TelegramBot\Entities;

use Illuminate\Database\Eloquent\Model;
use App\Traits\ModuleTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

class TelegramBots extends Model
{
    use ModuleTrait;

    protected $table = "bots";

    protected $fillable = ['name', 'key', 'module', 'database', 'username', 'password', 'token', 'data'];

    protected $casts = [
        'data' => 'json',
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
}
