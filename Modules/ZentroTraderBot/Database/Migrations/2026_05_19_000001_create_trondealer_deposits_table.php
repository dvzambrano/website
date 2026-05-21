<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    protected $connection = 'tenant';

    public function up(): void
    {
        $this->down();
        getModuleSchema()->create('trondealer_deposits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();

            // TronDealer swap identifiers
            $table->string('swap_id')->nullable()->unique()->index();
            $table->text('access_cookie')->nullable();
            $table->string('payout_address')->nullable();
            $table->timestamp('expires_at')->nullable();

            // Input (what the user sends)
            $table->string('wallet_address')->nullable()->index();  // deposit address
            $table->decimal('amount', 18, 8)->nullable();           // amount_in
            $table->string('asset', 10)->nullable();                // e.g. USDT
            $table->string('network', 10)->nullable();              // e.g. bsc

            // Output (what the escrow contract receives)
            $table->string('asset_out', 10)->nullable();            // USDC
            $table->string('chain_out', 10)->nullable();            // pol
            $table->decimal('amount_out', 18, 8)->nullable();
            $table->decimal('fee_pct', 8, 4)->nullable();

            // On-chain transaction
            $table->string('tx_hash')->unique()->nullable()->index();
            $table->unsignedInteger('block_number')->nullable();
            $table->unsignedSmallInteger('confirmations')->default(0);

            $table->enum('status', [
                'pending', 'waiting_deposit', 'deposit_detected', 'processing',
                'completed', 'expired', 'failed', 'refund_required', 'refunded', 'rejected',
            ])->default('pending')->index();

            $table->string('from_address')->nullable();
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('swept_at')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        getModuleSchema()->dropIfExists('trondealer_deposits');
    }
};
