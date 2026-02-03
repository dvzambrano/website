<?php

namespace Modules\Web3\Events;

use Illuminate\Queue\SerializesModels;

class BlockchainActivityDetected
{
    use SerializesModels;

    public $data;

    public function __construct(array $data)
    {
        // Pasamos el $activity del foreach de Alchemy
        $this->data = $data;
    }
}