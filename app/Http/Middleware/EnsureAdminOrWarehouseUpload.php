<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminOrWarehouseUpload
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isAdmin()) {
            return $next($request);
        }

        if ($user?->isWarehouseUser() && $user->warehouse_id) {
            return $next($request);
        }

        return response()->json(['message' => 'غير مصرح برفع البيانات.'], 403);
    }
}
