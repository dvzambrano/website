<?php

namespace Modules\ZentroPackageBot\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Laravel\Traits\TenantTrait;

class Histories extends Model
{
    use TenantTrait;

    protected $table = 'histories';

    protected $guarded = [];

    protected $casts = [
        'location' => 'array', // Laravel lo convierte automáticamente de JSON a Array de PHP
    ];

    /*
    protected $fillable = [
        'package_id', 'status', 'location', 'comment', 'user_id'
    ];
    */

    public function package()
    {
        return $this->belongsTo(Packages::class);
    }

    /**
     * El usuario (mensajero) que realizó el escaneo o cambio.
     */
    public function user()
    {
        return $this->belongsTo(Packages::class);
    }

    protected $appends = ['google_maps_url'];
    public function getGoogleMapsUrl()
    {
        if (!$this->location || !isset($this->location['lat'])) {
            return null;
        }

        $lat = $this->location['lat'];
        $lng = $this->location['lng'];

        // Retorna el link listo para abrir en cualquier móvil
        return "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}";
    }
}
