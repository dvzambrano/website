<?php

namespace Modules\ZentroOwnerBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Modules\ZentroOwnerBot\Services\SecurityService;

class ServicesController extends Controller
{
    /**
     * POST /webhook/generator
     * Body esperado: { "service": "gmail", "seed": "apple-river-stone" }
     * Respuesta: { "password": "xK9#mP2!qR5@Tz" }
     */
    public function processWebhook(Request $request): JsonResponse
    {
        // ── Validación ────────────────────────────────────────────────────
        $validator = Validator::make($request->all(), [
            'service' => 'required|string|min:1|max:100',
            'seed' => 'required|string|min:8|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Datos inválidos',
                'details' => $validator->errors(),
            ], 422);
        }

        $service = trim($request->input('service'));
        $seed = trim($request->input('seed'));

        // ── Generación de contraseña determinista ────────────────────────
        // Misma combinación service+seed → siempre la misma contraseña.
        // Usa HMAC-SHA256 para que sea criptográficamente seguro.
        $password = SecurityService::derivePassword($service, $seed);

        return response()->json([
            'password' => $password,
        ], 200);
    }
}