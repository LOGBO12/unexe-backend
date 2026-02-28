<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    // Inscription libre (candidats uniquement)
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|email|unique:users,email',
            'password'              => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'role'     => 'candidat',
        ]);

        $token = $user->createToken('unexe_token')->plainTextToken;

        return response()->json([
            'message' => 'Compte créé avec succès.',
            'user'    => $user,
            'token'   => $token,
        ], 201);
    }

    // Connexion
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt(['email' => $data['email'], 'password' => $data['password']])) {
            return response()->json([
                'message' => 'Email ou mot de passe incorrect.',
            ], 401);
        }

        $user  = Auth::user();
        $token = $user->createToken('unexe_token')->plainTextToken;

        return response()->json([
            'message' => 'Connecté avec succès.',
            'user'    => $user->load('candidate.department'),
            'token'   => $token,
        ]);
    }

    // Déconnexion
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    // Infos utilisateur connecté
    public function me(Request $request)
    {
        $user = $request->user()->load('candidate.department');

        // Vérifier si le profil doit être complété
        $needsProfileCompletion = (
            $user->role === 'candidat' &&
            $user->candidate?->status === 'validated' &&
            !$user->is_profile_complete
        );

        return response()->json([
            'user'                    => $user,
            'needs_profile_completion' => $needsProfileCompletion,
        ]);
    }
}