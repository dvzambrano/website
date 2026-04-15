<?php

namespace Modules\ZentroOwnerBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ServicesController extends Controller
{
    /**
     * POST /webhook/generator
     *
     * Body esperado:
     *   { "service": "gmail", "seed": "apple-river-stone" }
     *
     * Respuesta:
     *   { "password": "xK9#mP2!qR5@Tz" }
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
        $password = $this->derivePassword($service, $seed);


        Log::debug("🐞 ServicesController webhook", [
            "seed" => $seed,
            "service" => $service,
            "password" => $password,
        ]);

        return response()->json([
            'password' => $password,
        ], 200);
    }

    /**
     * Deriva una contraseña segura y determinista a partir del servicio y seed.
     *
     * Algoritmo:
     *  1. HMAC-SHA256(seed, service) → 32 bytes de entropía
     *  2. Se mapean a un alfabeto con mayúsculas, minúsculas, números y símbolos
     *  3. Se garantiza al menos un carácter de cada tipo
     *
     * @param  string $service  Nombre del servicio (ej: "gmail")
     * @param  string $seed     Frase semilla del usuario
     * @param  int    $length   Longitud de la contraseña (por defecto 16)
     * @return string
     */
    private function derivePassword(string $service, string $seed, int $length = 16): string
    {
        // Generamos el hash determinista: mismo input → mismo output siempre
        $hash = hash_hmac('sha256', strtolower($service), $seed, true);

        // Expandimos a suficientes bytes con SHA-512 si hacen falta
        $bytes = $hash . hash_hmac('sha512', $service . ':v1', $seed, true);

        // Alfabeto dividido por categorías para garantizar complejidad
        $uppercase = 'ABCDEFGHJKLMNPQRSTUVWXYZ';   // sin I/O para evitar confusión
        $lowercase = 'abcdefghjkmnpqrstuvwxyz';     // sin i/l/o
        $digits = '23456789';                    // sin 0/1 para evitar confusión
        $symbols = '!@#$%^&*-_+=?';
        $all = $uppercase . $lowercase . $digits . $symbols;

        $password = '';
        $pos = 0;

        // Garantizamos al menos 1 carácter de cada categoría
        $password .= $uppercase[ord($bytes[$pos++]) % strlen($uppercase)];
        $password .= $lowercase[ord($bytes[$pos++]) % strlen($lowercase)];
        $password .= $digits[ord($bytes[$pos++]) % strlen($digits)];
        $password .= $symbols[ord($bytes[$pos++]) % strlen($symbols)];

        // Rellenamos el resto hasta alcanzar $length
        while (strlen($password) < $length) {
            $password .= $all[ord($bytes[$pos++ % strlen($bytes)]) % strlen($all)];
        }

        // Mezclamos los caracteres de forma determinista (misma semilla → mismo orden)
        $seed_int = abs(crc32($seed . $service));
        srand($seed_int);
        $chars = str_split($password);
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = rand(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }
        srand(); // Restauramos la aleatoriedad del sistema

        return implode('', $chars);
    }
}