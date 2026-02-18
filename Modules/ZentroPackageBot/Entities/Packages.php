<?php

namespace Modules\ZentroPackageBot\Entities;

use Illuminate\Database\Console\Migrations\StatusCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Laravel\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use PhpParser\Node\Stmt\Static_;

class Packages extends Model
{
    use TenantTrait;
    use SoftDeletes;

    protected $table = 'packages';
    protected $guarded = [];


    /*
        protected $fillable = [
            'tracking_number',
            'awb',
            'internal_ref',
            'recipient_name',
            'recipient_id',
            'recipient_phone',
            'full_address',
            'destination_code',
            'province',
            'description',
            'weight_kg',
            'type',
            'pieces',
            'sender_name',
            'sender_email',
            'status'
        ];
        */

    /**
     * Obtiene todos los movimientos del paquete.
     */
    public function history()
    {
        return $this->hasMany(Histories::class)->orderBy('created_at', 'desc');
    }

    /**
     * Obtiene el último estado registrado.
     */
    public function lastHistory()
    {
        return $this->hasOne(Histories::class)->latestOfMany();
    }

    /**
     * Devuelve los campos usados para generar el seed del internal_ref de un paquete
     * Debe mantenerse el orden o el internal_ref cambia!!
     * @return string[]
     */
    public static function getSeedFields()
    {
        return [
            'recipient_id',
            'house',
            'weight_kg',
            'pieces'
        ];
    }


    protected $appends = ['fingerprint'];
    protected function fingerprint(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->awb)
                    return $this->awb;
                if ($this->tracking_number)
                    return $this->tracking_number;
                if ($this->internal_ref)
                    return $this->internal_ref;

                $seedString = "";
                $seedFields = Packages::getSeedFields();
                foreach ($seedFields as $field) {
                    $seedString .= $rowData[$field] ?? '';
                }
                $seed = strtoupper(substr(md5($seedString), 0, 12));

                return $seed;
            },
        );
    }
}
