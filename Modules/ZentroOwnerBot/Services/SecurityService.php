<?php

namespace Modules\ZentroOwnerBot\Services;

class SecurityService
{

    public static function generateHash($text, $key, $length = false, $debug = false): string
    {
        $key = strtolower($key);
        if ($debug) {
            echo $text . "\n" . $key . "\n";
        }
        $hash = hash_hmac('sha256', $text, $key);
        if ($length) {
            if ($length > 64) {
                $length = 64;
            }
            $hash = substr($hash, 0, $length);
        }
        // Convertir la primera letra del hash a mayúscula (si existe)
        $hash = preg_replace_callback(
            '/[a-z]/', // Busca la primera letra minúscula
            function ($matches) {
                return strtoupper($matches[0]); // Convierte a mayúscula
            },
            $hash,
            1// Solo la primera ocurrencia
        );
        if ($debug) {
            die($hash);
        }

        return $hash;
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
    public static function derivePassword(string $service, string $seed, int $length = 16): string
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