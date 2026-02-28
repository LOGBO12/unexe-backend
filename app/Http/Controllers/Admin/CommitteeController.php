<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommitteeMember;
use App\Models\CommitteePage;
use App\Models\ActionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CommitteeController extends Controller
{
    // Liste des membres
    public function index()
    {
        $members = CommitteeMember::with('user')
            ->orderBy('display_order')
            ->get();

        return response()->json($members);
    }

    // Ajouter un membre au comité
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id'       => 'required|exists:users,id',
            'position'      => 'required|string|max:255',
            'bio'           => 'nullable|string|max:500',
            'photo'         => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'display_order' => 'nullable|integer',
        ]);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('committee', 'public');
        }

        $member = CommitteeMember::create([
            'user_id'       => $data['user_id'],
            'position'      => $data['position'],
            'bio'           => $data['bio'] ?? null,
            'photo'         => $photoPath,
            'display_order' => $data['display_order'] ?? 0,
        ]);

        ActionLog::log($request->user(), 'add_committee_member', $member);

        return response()->json([
            'message' => 'Membre ajouté au comité.',
            'member'  => $member->load('user'),
        ], 201);
    }

    // Modifier un membre
    public function update(Request $request, int $id)
    {
        $member = CommitteeMember::findOrFail($id);

        $data = $request->validate([
            'position'      => 'sometimes|string|max:255',
            'bio'           => 'sometimes|string|max:500',
            'photo'         => 'sometimes|file|mimes:jpg,jpeg,png|max:2048',
            'display_order' => 'sometimes|integer',
        ]);

        if ($request->hasFile('photo')) {
            if ($member->photo) {
                Storage::disk('public')->delete($member->photo);
            }
            $data['photo'] = $request->file('photo')->store('committee', 'public');
        }

        $member->update($data);

        return response()->json([
            'message' => 'Membre mis à jour.',
            'member'  => $member->fresh()->load('user'),
        ]);
    }

    // Supprimer un membre
    public function destroy(Request $request, int $id)
    {
        $member = CommitteeMember::findOrFail($id);

        if ($member->photo) {
            Storage::disk('public')->delete($member->photo);
        }

        ActionLog::log($request->user(), 'remove_committee_member', $member);
        $member->delete();

        return response()->json(['message' => 'Membre retiré du comité.']);
    }

    // Obtenir le contenu de la page publique
    public function getPage()
    {
        $page = CommitteePage::first();

        return response()->json($page);
    }

    // Mettre à jour la page publique
    public function updatePage(Request $request)
    {
        $data = $request->validate([
            'project_description' => 'nullable|string',
            'vision'              => 'nullable|string',
            'objectives'          => 'nullable|array',
            'objectives.*'        => 'string',
            'team_photo'          => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
        ]);

        $page = CommitteePage::firstOrNew([]);

        if ($request->hasFile('team_photo')) {
            if ($page->team_photo) {
                Storage::disk('public')->delete($page->team_photo);
            }
            $data['team_photo'] = $request->file('team_photo')
                ->store('committee', 'public');
        }

        $data['updated_by'] = $request->user()->id;
        $page->fill($data)->save();

        ActionLog::log($request->user(), 'update_committee_page', $page);

        return response()->json([
            'message' => 'Page du comité mise à jour.',
            'page'    => $page->fresh(),
        ]);
    }
}