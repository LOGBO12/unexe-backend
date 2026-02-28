<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ProfileCompleteMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if ($user && $user->isCandidat() && !$user->is_profile_complete) {
            // Autoriser seulement la route de complétion du profil
            if (!$request->is('api/profile/complete')) {
                return response()->json([
                    'message' => 'Veuillez compléter votre profil avant de continuer.',
                    'redirect' => '/complete-profile'
                ], 403);
            }
        }

        return $next($request);
    }
}