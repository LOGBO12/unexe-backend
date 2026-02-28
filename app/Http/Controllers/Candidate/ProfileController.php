<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\ActionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    // Voir son profil
    public function show(Request $request)
    {
        $user = $request->user()->load('candidate.department');

        return response()->json($user);
    }

    // Compléter le profil (obligatoire après validation)
    public function complete(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'bio'     => 'required|string|min:50|max:500',
            'photo'   => 'required|file|mimes:jpg,jpeg,png|max:2048',
            'phone'   => 'nullable|string|max:20',
        ]);

        // Upload photo
        $photoPath = null;
        if ($request->hasFile('photo')) {
            // Supprimer l'ancienne photo si elle existe
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $photoPath = $request->file('photo')->store("avatars", 'public');
        }

        // Mettre à jour l'utilisateur
        $user->update([
            'name'                => $data['name'],
            'avatar'              => $photoPath ?? $user->avatar,
            'is_profile_complete' => true,
        ]);

        // Mettre à jour le profil candidat
        if ($user->candidate) {
            $user->candidate->update([
                'bio'   => $data['bio'],
                'photo' => $photoPath ?? $user->candidate->photo,
                'phone' => $data['phone'] ?? $user->candidate->phone,
            ]);
        }

        ActionLog::log(
            $user,
            'complete_profile',
            null,
            ['name' => $data['name']]
        );

        return response()->json([
            'message' => 'Profil complété avec succès. Bienvenue sur UNEXE !',
            'user'    => $user->fresh()->load('candidate.department'),
        ]);
    }

    // Mettre à jour son profil
    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'  => 'sometimes|string|max:255',
            'bio'   => 'sometimes|string|min:50|max:500',
            'photo' => 'sometimes|file|mimes:jpg,jpeg,png|max:2048',
            'phone' => 'sometimes|string|max:20',
        ]);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $photoPath = $request->file('photo')->store("avatars", 'public');
            $user->update(['avatar' => $photoPath]);
        }

        if (isset($data['name'])) {
            $user->update(['name' => $data['name']]);
        }

        if ($user->candidate) {
            $updates = array_filter([
                'bio'   => $data['bio'] ?? null,
                'phone' => $data['phone'] ?? null,
                'photo' => $photoPath,
            ]);
            if (!empty($updates)) {
                $user->candidate->update($updates);
            }
        }

        return response()->json([
            'message' => 'Profil mis à jour.',
            'user'    => $user->fresh()->load('candidate.department'),
        ]);
    }
}