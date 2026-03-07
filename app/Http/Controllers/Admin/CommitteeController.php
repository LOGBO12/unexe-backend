<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommitteeMember;
use App\Models\CommitteePage;
use App\Models\ActionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CommitteeController extends Controller
{
    public function index()
    {
        $members = CommitteeMember::with('user')
            ->orderBy('display_order')
            ->get();
        return response()->json($members);
    }

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
        return response()->json(['message' => 'Membre ajouté.', 'member' => $member->load('user')], 201);
    }

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
            if ($member->photo) Storage::disk('public')->delete($member->photo);
            $data['photo'] = $request->file('photo')->store('committee', 'public');
        }
        $member->update($data);
        return response()->json(['message' => 'Membre mis à jour.', 'member' => $member->fresh()->load('user')]);
    }

    public function destroy(Request $request, int $id)
    {
        $member = CommitteeMember::findOrFail($id);
        if ($member->photo) Storage::disk('public')->delete($member->photo);
        ActionLog::log($request->user(), 'remove_committee_member', $member);
        $member->delete();
        return response()->json(['message' => 'Membre retiré.']);
    }

    public function getPage()
    {
        $page = CommitteePage::first();
        if (!$page) {
            return response()->json([
                'project_description' => null,
                'vision'              => null,
                'objectives'          => [],
                'team_photo'          => null,
                'team_photo_url'      => null,
            ]);
        }
        return response()->json([
            'id'                  => $page->id,
            'project_description' => $page->project_description,
            'vision'              => $page->vision,
            'objectives'          => $page->objectives ?? [],
            'team_photo'          => $page->team_photo,
            'team_photo_url'      => $page->team_photo ? asset('storage/' . $page->team_photo) : null,
        ]);
    }

    // Ajouter cette méthode dans CommitteeController
public function availableUsers()
{
    // Tous les users avec rôle comite OU super_admin
    $alreadyAdded = CommitteeMember::pluck('user_id')->toArray();

    $users = \App\Models\User::whereIn('role', ['comite', 'super_admin'])
        ->whereNotIn('id', $alreadyAdded)
        ->get(['id', 'name', 'email', 'role', 'avatar']);

    return response()->json($users);
}

    public function updatePage(Request $request)
    {
        Log::info('[CommitteePage] updatePage — hasFile: ' . ($request->hasFile('team_photo') ? 'OUI' : 'NON'));
        $data = $request->validate([
            'project_description' => 'nullable|string',
            'vision'              => 'nullable|string',
            'objectives'          => 'nullable|array',
            'objectives.*'        => 'nullable|string',
            'team_photo'          => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);
        $page = CommitteePage::first() ?? new CommitteePage();
        $page->project_description = $data['project_description'] ?? $page->project_description;
        $page->vision              = $data['vision']              ?? $page->vision;
        if (array_key_exists('objectives', $data)) {
            $page->objectives = array_values(
                array_filter($data['objectives'] ?? [], fn($o) => trim((string)$o) !== '')
            );
        }
        if ($request->hasFile('team_photo') && $request->file('team_photo')->isValid()) {
            if ($page->team_photo && Storage::disk('public')->exists($page->team_photo)) {
                Storage::disk('public')->delete($page->team_photo);
            }
            $path = $request->file('team_photo')->store('committee', 'public');
            $page->team_photo = $path;
            Log::info('[CommitteePage] Photo stockée : ' . $path);
        }
        $page->updated_by = $request->user()->id;
        $page->save();
        ActionLog::log($request->user(), 'update_committee_page', $page);
        return response()->json([
            'message'             => 'Page mise à jour.',
            'project_description' => $page->project_description,
            'vision'              => $page->vision,
            'objectives'          => $page->objectives ?? [],
            'team_photo'          => $page->team_photo,
            'team_photo_url'      => $page->team_photo ? asset('storage/' . $page->team_photo) : null,
        ]);
    }
}
