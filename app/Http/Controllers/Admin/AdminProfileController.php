<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AdminProfileController extends Controller
{
    // Voir son profil
    public function show(Request $request)
    {
        return response()->json([
            'user' => $request->user()
        ]);
    }

    // Mettre à jour nom + photo
    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->hasFile('photo')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = $request->file('photo')
                ->store('avatars', 'public');
        }

        $user->update([
            'name'   => $data['name'],
            'avatar' => $data['avatar'] ?? $user->avatar,
        ]);

        return response()->json([
            'message' => 'Profil mis à jour.',
            'user'    => $user->fresh(),
        ]);
    }

    // Changer le mot de passe
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password'      => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        // Vérifier l'ancien mot de passe
        if (!Hash::check($data['current_password'], $user->password)) {
            return response()->json([
                'errors' => [
                    'current_password' => ['Mot de passe actuel incorrect.']
                ]
            ], 422);
        }

        $user->update([
            'password' => Hash::make($data['password'])
        ]);

        return response()->json([
            'message' => 'Mot de passe modifié avec succès.'
        ]);
    }
}