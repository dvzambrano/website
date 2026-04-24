<?php

namespace Modules\ZentroTraderBot\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Laravel\Traits\TenantTrait;
use Modules\ZentroTraderBot\Entities\Offers;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Carbon\Carbon;

/**
 * @property int $id
 * @property int $offer_id
 * @property int $rater_user_id
 * @property int $rated_user_id
 * @property int $stars
 * @property string|null $comment
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Offers $offer
 * @property Suscriptions $rater
 * @property Suscriptions $rated
 */
class OffersRatings extends Model
{
    use TenantTrait;

    use HasFactory;

    protected $table = 'offers_ratings';

    protected $fillable = [
        'offer_id',
        'rater_user_id',
        'rated_user_id',
        'stars',
        'comment'
    ];

    /**
     * Relación con la oferta calificada
     */
    public function offer()
    {
        return $this->belongsTo(Offers::class, 'offer_id');
    }

    /**
     * Relación con el usuario que emite la calificación (El que vota)
     */
    public function rater()
    {
        // Asumiendo que usas el modelo Suscriptions para los perfiles de Telegram
        return $this->belongsTo(Suscriptions::class, 'rater_user_id', 'user_id');
    }

    /**
     * Relación con el usuario que recibe la calificación (El calificado)
     */
    public function rated()
    {
        return $this->belongsTo(Suscriptions::class, 'rated_user_id', 'user_id');
    }

    /**
     * Scope para obtener solo calificaciones de un número específico de estrellas
     */
    public function scopeByStars($query, $stars)
    {
        return $query->where('stars', $stars);
    }
}
