<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Isola as áreas exclusivas do administrador principal
 * (cadastro de usuários e logs do sistema).
 */
class EnsureIsMainAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user !== null && $user->isMainAdmin(),
            403,
            'Esta área é exclusiva do administrador principal.',
        );

        return $next($request);
    }
}
