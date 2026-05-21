<?php

namespace Modules\ZentroTraderBot\Jobs;

use Dvzambrano\TronDealer\Facades\TronDealer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\TelegramBot\Entities\TelegramBots;
use Modules\ZentroTraderBot\Entities\TronDealerDeposit;
use Modules\ZentroTraderBot\Services\DepositService;

class CheckSwapStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const MAX_ATTEMPTS = 30;

    protected int $depositId;
    protected string $tenantKey;
    protected int $attempt;

    public function __construct(int $depositId, string $tenantKey, int $attempt = 0)
    {
        $this->depositId = $depositId;
        $this->tenantKey = $tenantKey;
        $this->attempt = $attempt;
    }

    public function handle(): void
    {
        $tenant = TelegramBots::where('key', $this->tenantKey)->first();
        if (!$tenant) {
            Log::error('[CheckSwapStatus] Tenant not found', ['key' => $this->tenantKey]);
            return;
        }
        $tenant->connectToThisTenant();

        $deposit = TronDealerDeposit::find($this->depositId);
        if (!$deposit) {
            Log::warning('[CheckSwapStatus] Deposit not found', ['id' => $this->depositId]);
            return;
        }

        // Already in a terminal state — nothing to do
        if (in_array($deposit->status, DepositService::TERMINAL_STATUSES)) {
            return;
        }

        // Max attempts reached — force-expire
        if ($this->attempt >= self::MAX_ATTEMPTS) {
            $deposit->update(['status' => 'expired']);
            NotifySwapResult::dispatch($this->depositId, $this->tenantKey);
            return;
        }

        try {
            $response = TronDealer::getSwap($deposit->swap_id, $deposit->access_cookie);
            $swap = $response['swap'] ?? [];
            /*
            {
                "success": true,
                "authorized": false,
                "swap": {
                    "id": "b73ce0ed-6b2d-47ef-9dd6-d810a7abf2c7",
                    "asset_in": "USDT",
                    "chain_in": "bsc",
                    "asset_out": "USDC",
                    "chain_out": "pol",
                    "amount_in": 5,
                    "amount_out": 4.995,
                    "fee_pct": 0.1,
                    "deposit_address": null,
                    "status": "payout_sent",
                    "expires_at": "2026-05-21T21:52:13.537+00:00",
                    "created_at": "2026-05-21T21:22:13.58034+00:00"
                }
            }
            */
        } catch (\Throwable $e) {
            Log::error('[CheckSwapStatus] getSwap error', [
                'deposit_id' => $this->depositId,
                'attempt' => $this->attempt,
                'error' => $e->getMessage(),
            ]);
            // Retry anyway — transient API error
            $this->reschedule();
            return;
        }

        $previousStatus = $deposit->status;

        $status = isset($swap['status']) && $swap['status'] !== ''
            ? strtolower($swap['status'])
            : $previousStatus;

        // TronDealer uses payout_sent and completed interchangeably
        if ($status === 'payout_sent') {
            $status = 'completed';
        }

        if (env("DEBUG_MODE", false)) {
            Log::debug('🐞 [CheckSwapStatus] Swap polled', [
                'deposit_id' => $this->depositId,
                'attempt' => $this->attempt,
                'swap' => $swap,
            ]);
        }

        $updates = ['status' => $status];
        if (!empty($swap['deposit_tx_hash'])) {
            $updates['tx_hash'] = $swap['deposit_tx_hash'];
            $updates['detected_at'] = $deposit->detected_at ?? now();
        }
        if ($status === 'completed' || $status === 'processing') {
            $updates['confirmed_at'] = $deposit->confirmed_at ?? now();
        }
        $deposit->update($updates);

        if (in_array($status, DepositService::TERMINAL_STATUSES)) {
            NotifySwapResult::dispatch($this->depositId, $this->tenantKey);
            return;
        }

        // Notify on intermediate state transitions (only once per transition)
        if ($previousStatus !== $status && \in_array($status, ['deposit_detected', 'processing'])) {
            NotifySwapResult::dispatch($this->depositId, $this->tenantKey);
        }

        $this->reschedule();
    }

    private function reschedule(): void
    {
        self::dispatch($this->depositId, $this->tenantKey, $this->attempt + 1)
            ->delay(now()->addMinute());
    }
}
