<?php

namespace Modules\ZentroTraderBot\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Laravel\Traits\TenantTrait;

class TronDealerDeposit extends Model
{
    use TenantTrait;

    protected $table = 'trondealer_deposits';

    protected $fillable = [
        'user_id',
        'swap_id',
        'access_cookie',
        'payout_address',
        'expires_at',
        'wallet_address',
        'amount',
        'asset',
        'network',
        'asset_out',
        'chain_out',
        'amount_out',
        'fee_pct',
        'tx_hash',
        'block_number',
        'confirmations',
        'status',
        'from_address',
        'detected_at',
        'confirmed_at',
        'swept_at',
        'metadata',
    ];

    protected $casts = [
        'amount'        => 'decimal:8',
        'amount_out'    => 'decimal:8',
        'fee_pct'       => 'decimal:4',
        'confirmations' => 'integer',
        'block_number'  => 'integer',
        'metadata'      => 'array',
        'expires_at'    => 'datetime',
        'detected_at'   => 'datetime',
        'confirmed_at'  => 'datetime',
        'swept_at'      => 'datetime',
    ];
}
