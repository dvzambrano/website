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

    protected int    $depositId;
    protected string $tenantKey;
    protected int    $attempt;

    public function __construct(int $depositId, string $tenantKey, int $attempt = 0)
    {
        $this->depositId = $depositId;
        $this->tenantKey = $tenantKey;
        $this->attempt   = $attempt;
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
            $swap = TronDealer::getSwap($deposit->swap_id, $deposit->access_cookie);
        } catch (\Throwable $e) {
            Log::error('[CheckSwapStatus] getSwap error', [
                'deposit_id' => $this->depositId,
                'attempt'    => $this->attempt,
                'error'      => $e->getMessage(),
            ]);
            // Retry anyway — transient API error
            $this->reschedule();
            return;
        }

        $previousStatus = $deposit->status;
        $status = strtolower($swap['status'] ?? $deposit->status);

        $updates = ['status' => $status];
        if (!empty($swap['deposit_tx_hash'])) {
            $updates['tx_hash']      = $swap['deposit_tx_hash'];
            $updates['detected_at']  = $deposit->detected_at ?? now();
        }
        if ($status === 'completed' || $status === 'processing') {
            $updates['confirmed_at'] = $deposit->confirmed_at ?? now();
        }
        $deposit->update($updates);

        if (\in_array($status, DepositService::TERMINAL_STATUSES)) {
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
