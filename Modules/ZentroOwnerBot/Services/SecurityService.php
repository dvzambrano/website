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
        $upper = "ABCDEFGHJKLMNPQRSTUVWXYZ";
        $lower = "abcdefghjkmnpqrstuvwxyz";
        $digits = "23456789";
        $symbols = "!@#$%^&*-_+=?";
        $all = $upper . $lower . $digits . $symbols;

        // Generar entropía (96 bytes en total)
        $h256 = hash_hmac('sha256', strtolower($service), $seed, true);
        $h512 = hash_hmac('sha512', $service . ':v1', $seed, true);
        $entropy = $h256 . $h512;

        // 1. Construir base de 16 caracteres
        $pass = "";
        $pass .= $upper[ord($entropy[0]) % strlen($upper)];
        $pass .= $lower[ord($entropy[1]) % strlen($lower)];
        $pass .= $digits[ord($entropy[2]) % strlen($digits)];
        $pass .= $symbols[ord($entropy[3]) % strlen($symbols)];

        for ($i = 4; $i < 16; $i++) {
            $pass .= $all[ord($entropy[$i]) % strlen($all)];
        }

        // 2. Shuffle manual determinista (Fisher-Yates usando los bytes del hash)
        // Empezamos a usar bytes desde la posición 20 para no repetir los del inicio
        $chars = str_split($pass);
        $bytePos = 20;
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = ord($entropy[$bytePos++]) % ($i + 1);
            $tmp = $chars[$i];
            $chars[$i] = $chars[$j];
            $chars[$j] = $tmp;
        }

        return implode('', $chars);
    }
}