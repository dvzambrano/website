<?php

use Illuminate\Database\Migrations\Migration;

class CreateOffersTable extends Migration
{
    protected $connection = 'tenant';
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $this->down();
        getModuleSchema()->create('offers', function ($table) {
            $table->id();
            $table->uuid('uuid')->unique()->index(); // Identificador público único para soporte y seguimiento externo
            $table->unsignedBigInteger('user_id'); // Creador del anuncio

            // --- Datos Económicos ---
            $table->enum('type', ['buy', 'sell']);  // ¿El usuario quiere comprar o vender USD? 
            $table->decimal('amount', 20, 4); // Cantidad total disponible. IMPORTANTE: Cambia a 18 decimales para precisión cripto
            $table->decimal('price_per_usd', 16, 2);   // Precio (ej: 1.05 si cobras recargo)
            $table->string('payment_method');          // Zelle, Bizum, Transf. Local
            $table->string('currency')->default('USD');
            // --- Información de Pago (FIAT) ---
            $table->text('payment_details')->nullable(); // La cuenta donde el creador de la oferta quiere recibir

            // --- Estados Sincronizados con Solidity ---
            // Añadimos: LOCKED (en curso), DISPUTED (litigio)
            $table->enum('status', [
                'locked',    // LOCKED en contrato
                'disputed',  // DISPUTED en contrato
                'completed', // COMPLETED (fondos liberados)
                'cancelled', // CANCELLED (fondos devueltos)
                'signed',     // Solo local (oculto en el bot)
                'solved',     // Solo local (oculto en el bot)
                'expired',     // Solo local (oculto en el bot)
            ])->default('locked');

            // --- Identificación en Blockchain ---
            // Importante: Para 'buy', esto puede ser NULL inicialmente si no hay depósito en Escrow previo
            $table->unsignedBigInteger('blockchain_trade_id')->nullable()->index();
            $table->integer('network_id')->default(137); // Polygon por defecto
            $table->string('token_address')->nullable(); // Contrato del token (USDC, MATIC, etc.)

            // --- Actores del P2P ---
            $table->string('seller_address')->nullable()->index(); // Wallet del que deposita
            $table->string('buyer_address')->nullable()->index();  // Wallet del que recibe
            $table->text('winner_address')->nullable(); // Wallet que gana la disputa si la hubiera

            // --- Auditoría ---
            $table->string('tx_hash_deposit')->nullable();  // Hash del createTrade
            $table->string('tx_hash_release')->nullable();  // Hash de la liberación/resolución

            // --- Datos extra ---
            $table->jsonb('data');

            $table->timestamps();

            $table->index(['type', 'status', 'blockchain_trade_id', 'payment_method']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        getModuleSchema()->dropIfExists('offers');
    }
}
