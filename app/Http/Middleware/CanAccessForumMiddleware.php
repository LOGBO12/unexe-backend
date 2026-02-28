<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CanAccessForumMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        if (!$user->canAccessForum()) {
            return response()->json([
                'message' => 'Accès réservé aux candidats validés et membres du comité.'
            ], 403);
        }

        return $next($request);
    }
}