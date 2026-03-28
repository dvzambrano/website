<?php

namespace Modules\ZentroTraderBot\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Laravel\Traits\TenantTrait;

class Paymentmethods extends Model
{
    use TenantTrait;

    protected $table = 'paymentmethods';
    protected $fillable = ['name', 'identifier', 'icon'];

    /**
     * Relación inversa con las monedas.
     */
    public function currencies()
    {
        return $this->belongsToMany(
            Currencies::class,
            'currencypaymentmethods',
            'payment_method_id',    // Llave foránea de este modelo en la pivot
            'currency_id'           // Llave foránea del otro modelo en la pivot
        )
            ->withPivot(['min_limit', 'max_limit', 'instructions', 'is_active'])
            ->withTimestamps();
    }
}
