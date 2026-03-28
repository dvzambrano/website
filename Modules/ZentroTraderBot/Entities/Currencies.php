<?php

namespace Modules\ZentroTraderBot\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Laravel\Traits\TenantTrait;

class Currencies extends Model
{
    use TenantTrait;

    protected $table = 'currencies';
    protected $fillable = ['code', 'name', 'symbol', 'is_active'];

    /**
     * Relación con los Métodos de Pago a través de la tabla pivot.
     */
    public function paymentmethods()
    {
        return $this->belongsToMany(
            Paymentmethods::class,
            'currencypaymentmethods',
            'currency_id',          // Llave foránea de este modelo en la pivot
            'payment_method_id'     // Llave foránea del otro modelo en la pivot
        )
            ->withPivot(['min_limit', 'max_limit', 'instructions', 'is_active'])
            ->withTimestamps();
    }

    /**
     * Solo métodos activos para esta moneda.
     */
    public function activePaymentmethods()
    {
        return $this->paymentmethods()->wherePivot('is_active', true);
    }
}
