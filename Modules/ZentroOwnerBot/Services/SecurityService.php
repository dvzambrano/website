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

    public static function derivePassword($service, $seed)
    {
        // 1. Normalización inmediata
        $service = strtolower(trim($service));

        $upper = "ABCDEFGHJKLMNPQRSTUVWXYZ";
        $lower = "abcdefghjkmnpqrstuvwxyz";
        $digits = "23456789";
        $symbols = "!@#$%^&*-_+=?";
        $all = $upper . $lower . $digits . $symbols;

        // 2. Generar entropía (Usando el mismo service para ambos)
        $h256 = hash_hmac('sha256', $service, $seed, true);
        $h512 = hash_hmac('sha512', $service . ':v1', $seed, true);
        $entropy = $h256 . $h512;

        // 3. Construcción base
        $pass = "";
        $pass .= $upper[ord($entropy[0]) % strlen($upper)];
        $pass .= $lower[ord($entropy[1]) % strlen($lower)];
        $pass .= $digits[ord($entropy[2]) % strlen($digits)];
        $pass .= $symbols[ord($entropy[3]) % strlen($symbols)];

        for ($i = 4; $i < 16; $i++) {
            $pass .= $all[ord($entropy[$i]) % strlen($all)];
        }

        // 4. Shuffle manual (Fisher-Yates)
        $chars = str_split($pass);
        $bytePos = 20;
        for ($i = 15; $i > 0; $i--) {
            // Usamos el byte para decidir la posición j
            $j = ord($entropy[$bytePos++]) % ($i + 1);
            $tmp = $chars[$i];
            $chars[$i] = $chars[$j];
            $chars[$j] = $tmp;
        }

        return implode('', $chars);
    }
}