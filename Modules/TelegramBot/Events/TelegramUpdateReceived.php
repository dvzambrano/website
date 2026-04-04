<?php

namespace Modules\TelegramBot\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TelegramUpdateReceived
{
    use Dispatchable, SerializesModels;

    public $update;
    public $tenantKey;

    public function __construct(string $tenant_key, array $update)
    {
        $this->tenantKey = $tenant_key;
        $this->update = $update;
    }
}