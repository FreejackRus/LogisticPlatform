<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFreightRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_if(! $user, 403);
        abort_if($user->is_blocked || ! $user->is_active, 403, 'Доступ к платформе ограничен.');
        abort_if(! in_array($user->role, $roles, true), 403);

        return $next($request);
    }
}
