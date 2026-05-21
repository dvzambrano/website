<?php

namespace Modules\ZentroTraderBot\Services;

use Dvzambrano\TronDealer\Facades\TronDealer;
use Illuminate\Support\Facades\Log;
use Modules\ZentroTraderBot\Entities\TronDealerDeposit;

class DepositService
{
    public const ASSET_OUT = 'USDC';
    public const CHAIN_OUT = 'pol';

    // Statuses considered "active" (swap is live and awaiting funds or processing)
    public const ACTIVE_STATUSES = ['waiting_deposit', 'deposit_detected', 'processing'];

    // TronDealer terminal statuses
    public const TERMINAL_STATUSES = ['completed', 'expired', 'failed', 'refund_required', 'refunded', 'rejected'];

    public function getActiveDeposit(int $userId): ?TronDealerDeposit
    {
        return TronDealerDeposit::where('user_id', $userId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
    }

    /**
     * Returns the swap pairs available for input, always targeting USDC on pol as output.
     * Returns an array of ['label' => 'USDT (BSC)', 'asset_in' => 'USDT', 'chain_in' => 'bsc', 'min' => ..., 'max' => ..., 'fee_pct' => ...]
     */
    public function getAvailableInputPairs(): array
    {
        $response = TronDealer::getSwapPairs();

        if (!($response['success'] ?? false) || empty($response['pairs'])) {
            return [];
        }

        $pairs = [];
        foreach ($response['pairs'] as $pair) {
            if (
                strtoupper($pair['asset_out']) === self::ASSET_OUT &&
                strtolower($pair['chain_out']) === self::CHAIN_OUT
            ) {
                $pairs[] = [
                    'label' => strtoupper($pair['asset_in']) . ' (' . strtoupper($pair['chain_in']) . ')',
                    'asset_in' => strtoupper($pair['asset_in']),
                    'chain_in' => strtolower($pair['chain_in']),
                    'min' => (float) $pair['min_amount'],
                    'max' => (float) $pair['max_amount'],
                    'fee_pct' => (float) $pair['fee_pct'],
                ];
            }
        }

        return $pairs;
    }

    public function getQuote(string $assetIn, string $chainIn, float $amountIn): array
    {
        return TronDealer::getSwapQuote($assetIn, $chainIn, self::ASSET_OUT, self::CHAIN_OUT, $amountIn);
    }

    /**
     * Applies TronDealer's fee to compute the amount that actually
     * arrives at the payout address after all intermediate hops.
     */
    public function computeAdjustedAmountOut(float $amountOut, float $feePct): float
    {
        return $amountOut * (1 - $feePct / 100);
    }

    /**
     * Creates a TronDealer swap and persists it to the DB.
     * Throws on any API or DB error.
     */
    public function createSwap(
        int $userId,
        string $assetIn,
        string $chainIn,
        float $amountIn,
        string $quoteId,
        float $amountOut,
        float $feePct
    ): TronDealerDeposit {
        $payoutAddress = config('zentrotraderbot.trondealer_payout_address');

        // Persist before calling TronDealer so we have a record even on partial failure
        $deposit = TronDealerDeposit::create([
            'user_id' => $userId,
            'status' => 'pending',
            'amount' => $amountIn,
            'asset' => $assetIn,
            'network' => $chainIn,
            'asset_out' => self::ASSET_OUT,
            'chain_out' => self::CHAIN_OUT,
            'amount_out' => $amountOut,
            'fee_pct' => $feePct,
            'payout_address' => $payoutAddress,
        ]);

        try {
            $result = TronDealer::createSwap($quoteId, $payoutAddress);
            $swap = $result['swap'];

            $deposit->update([
                'swap_id' => $swap['id'],
                'access_cookie' => $result['_access_cookie'] ?? null,
                'wallet_address' => $swap['deposit_address'],
                'expires_at' => $swap['expires_at'],
                'status' => 'waiting_deposit',
            ]);
        } catch (\Throwable $e) {
            $deposit->update(['status' => 'failed']);
            Log::error('[DepositService] createSwap failed', [
                'user_id' => $userId,
                'quote_id' => $quoteId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $deposit->fresh();
    }
}
