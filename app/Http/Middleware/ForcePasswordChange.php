<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $debeForzar = $user
            && $user->must_change_password
            && $request->isMethod('get')
            && ! $request->routeIs('security.edit')
            && ! $request->routeIs('logout');

        if ($debeForzar) {
            return redirect()->route('security.edit')
                ->with('status', 'Debes cambiar tu contraseña temporal antes de continuar.');
        }

        return $next($request);
    }
}