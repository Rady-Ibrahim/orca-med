<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! in_array($user->role?->value, $roles, true)) {
            abort(403, 'ليس لديك صلاحية الوصول لهذه الصفحة.');
        }

        return $next($request);
    }
}
