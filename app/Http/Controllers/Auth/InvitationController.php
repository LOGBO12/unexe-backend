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
        ]);

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

        // Si candidat invité, créer l'entrée candidate
        if ($data['role'] === 'candidat' && !empty($data['department_id'])) {
            Candidate::create([
                'user_id'       => $user->id,
                'department_id' => $data['department_id'],
                'status'        => 'validated', // candidat direct = déjà validé
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
            ['email' => $data['email'], 'department_id' => $data['department_id'] ?? null]
        );

        return response()->json([
            'message' => 'Invitation envoyée avec succès.',
        ], 201);
    }

    // Vérifier un token d'invitation
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

    // Annuler une invitation
    public function cancel(Request $request, int $id)
    {
        $invitation = Invitation::findOrFail($id);

        if ($invitation->isUsed()) {
            return response()->json(['message' => 'Invitation déjà utilisée, impossible d\'annuler.'], 422);
        }

        $invitation->delete();

        return response()->json(['message' => 'Invitation annulée.']);
    }
}