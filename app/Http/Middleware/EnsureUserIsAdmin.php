<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deja pasar solo a usuarios autenticados con el rol 'admin'.
 * Va después de 'auth': un invitado nunca llega aquí.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user && $user->isAdmin(), 403, 'No tienes permiso para ver esta sección.');

        return $next($request);
    }
}
