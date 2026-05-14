<?php

namespace Modules\ZentroTraderBot\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\TelegramBot\Entities\TelegramBots;
use Modules\Laravel\Services\BehaviorService;

class DeleteMoralisStream extends Command
{
    protected $signature = 'zentrotraderbot:moralis-delete-stream {id=d84f7739-9723-4616-a000-82c0d82d5920}';
    protected $description = 'Elimina un Stream en Moralis dado su ID';

    public function handle()
    {
        $apiKey = env("MORALIS_API_KEY");
        $id = $this->argument('id');

        $this->info("🗑 Eliminando Stream existente: {$id}");
        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'Content-Type' => 'application/json',
        ])
            ->timeout(BehaviorService::timeout())
            ->delete("https://api.moralis-streams.com/streams/evm/{$id}");

        if ($response->successful()) {
            $this->info("🪦 Stream eliminado!");
        } else {
            $this->error("❌ Error eliminando Stream: " . $response->body());
        }
    }
}