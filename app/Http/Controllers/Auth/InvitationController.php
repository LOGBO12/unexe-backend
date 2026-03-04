<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Candidate;
use App\Models\ActionLog;
use App\Mail\CommitteeMemberInvitation;
use App\Mail\CandidateInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

class InvitationController extends Controller
{
    // Envoyer une invitation
    public function send(Request $request)
    {
        $data = $request->validate([
            'email'         => 'required|email',
            'role'          => 'required|in:comite,candidat',
            'department_id' => 'nullable|exists:departments,id',
            'year'          => 'nullable|in:1,2', // ← AJOUT
        ]);

        // Si candidat : département ET année obligatoires
        if ($data['role'] === 'candidat') {
            if (empty($data['department_id'])) {
                return response()->json(['message' => 'Le département est obligatoire pour un candidat.'], 422);
            }
            if (empty($data['year'])) {
                return response()->json(['message' => "L'année d'étude est obligatoire pour un candidat."], 422);
            }
        }

        // Vérifier que l'email n'est pas déjà utilisé
        if (User::where('email', $data['email'])->exists()) {
            return response()->json([
                'message' => 'Un compte existe déjà avec cet email.'
            ], 422);
        }

        // Vérifier qu'une invitation valide n'existe pas déjà
        $existing = Invitation::where('email', $data['email'])
            ->whereNull('used_at')
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Une invitation est déjà en attente pour cet email.'
            ], 422);
        }

        // Générer mot de passe et token
        $defaultPassword = Str::random(10);
        $token           = Str::uuid();

        $invitation = Invitation::create([
            'email'            => $data['email'],
            'role'             => $data['role'],
            'token'            => $token,
            'default_password' => Hash::make($defaultPassword),
            'invited_by'       => $request->user()->id,
            'department_id'    => $data['department_id'] ?? null,
            'expires_at'       => Carbon::now()->addHours(48),
        ]);

        // Créer le compte utilisateur directement
        $user = User::create([
            'name'                  => 'Utilisateur UNEXE',
            'email'                 => $data['email'],
            'password'              => Hash::make($defaultPassword),
            'role'                  => $data['role'],
            'invited_by'            => $request->user()->id,
            'is_profile_complete'   => false,
        ]);

        // Si candidat invité, créer l'entrée candidate avec département ET année
        if ($data['role'] === 'candidat' && !empty($data['department_id'])) {
            Candidate::create([
                'user_id'       => $user->id,
                'department_id' => $data['department_id'],
                'year'          => $data['year'],   // ← AJOUT
                'status'        => 'validated',      // candidat direct = déjà validé
                'validated_by'  => $request->user()->id,
                'validated_at'  => Carbon::now(),
            ]);
        }

        // Envoyer l'email selon le rôle
        if ($data['role'] === 'comite') {
            Mail::to($data['email'])->send(
                new CommitteeMemberInvitation($invitation, $defaultPassword)
            );
        } else {
            Mail::to($data['email'])->send(
                new CandidateInvitation($invitation->load('department'), $defaultPassword)
            );
        }

        // Logger l'action
        ActionLog::log(
            $request->user(),
            $data['role'] === 'comite' ? 'invite_comite' : 'invite_candidat',
            $user,
            ['email' => $data['email'], 'department_id' => $data['department_id'] ?? null, 'year' => $data['year'] ?? null] // ← year dans le log
        );

        return response()->json([
            'message' => 'Invitation envoyée avec succès.',
        ], 201);
    }

    public function checkToken(string $token)
    {
        $invitation = Invitation::where('token', $token)->first();

        if (!$invitation) {
            return response()->json(['message' => 'Invitation introuvable.'], 404);
        }

        if (!$invitation->isValid()) {
            return response()->json(['message' => 'Invitation expirée ou déjà utilisée.'], 410);
        }

        return response()->json([
            'email' => $invitation->email,
            'role'  => $invitation->role,
        ]);
    }

    // Liste des invitations envoyées
    public function index(Request $request)
    {
        $invitations = Invitation::with('invitedBy', 'department')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($invitations);
    }

    public function cancel(Request $request, int $id)
    {
        $invitation = Invitation::findOrFail($id);

        if ($invitation->used_at) {
            return response()->json(['message' => 'Invitation déjà utilisée.'], 422);
        }

        $user = User::where('email', $invitation->email)
                    ->where('is_profile_complete', false)
                    ->first();

        if ($user) {
            $user->delete();
        }

        $invitation->delete();

        return response()->json(['message' => 'Invitation annulée.']);
    }

    public function activate(Request $request, string $token)
    {
        $data = $request->validate([
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required',
        ]);

        $invitation = Invitation::where('token', $token)->first();

        if (!$invitation) {
            return response()->json(['message' => 'Invitation introuvable.'], 404);
        }

        if (!$invitation->isValid()) {
            return response()->json(['message' => 'Invitation expirée ou déjà utilisée.'], 410);
        }

        $user = User::where('email', $invitation->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Utilisateur introuvable.'], 404);
        }

        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        $invitation->update(['used_at' => Carbon::now()]);

        return response()->json([
            'message' => 'Compte activé avec succès.',
            'email'   => $invitation->email,
            'role'    => $invitation->role,
        ]);
    }
}