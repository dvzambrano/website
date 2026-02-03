<?php

namespace Modules\Web3\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class AlchemyController extends Controller
{
    public function webhook()
    {
        try {
            $payload = request()->all();
            // Logueamos siempre para auditoría interna
            Log::info("Alchemy Webhook - Evento recibido:" . json_encode($payload));

        } catch (\Exception $e) {
            // Logueamos el error pero devolvemos 200 para que Alchemy no marque el webhook como "Caído"
            Log::error("Error procesando Webhook de Alchemy: " . $e->getMessage());
        }


        // Si el soporte de Alchemy está probando el endpoint, 
        // esto responderá con éxito antes de intentar procesar lógica compleja.
        return response()->json([
            'status' => 'success',
            'timestamp' => now()->toIso8601String()
        ], 200);
    }
}
