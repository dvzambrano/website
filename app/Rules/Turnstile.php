<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Valida el token de Cloudflare Turnstile enviado por el widget del formulario.
 * Si el secreto no está configurado, falla cerrado: nunca deja pasar sin verificar.
 */
class Turnstile implements ValidationRule
{
    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(private ?string $remoteIp = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secret = config('services.turnstile.secret');

        if (! filled($secret)) {
            Log::error('Turnstile: falta TURNSTILE_SECRET_KEY; no se puede verificar el registro.');
            $fail('No se pudo verificar la confirmación anti-robot. Intenta de nuevo más tarde.');

            return;
        }

        if (! is_string($value) || $value === '') {
            $fail('Confirma que no eres un robot.');

            return;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post(self::VERIFY_URL, array_filter([
                    'secret' => $secret,
                    'response' => $value,
                    'remoteip' => $this->remoteIp,
                ]));

            $valid = $response->successful() && $response->json('success') === true;
        } catch (Throwable $e) {
            report($e);
            $valid = false;
        }

        if (! $valid) {
            $fail('Confirma que no eres un robot.');
        }
    }
}
