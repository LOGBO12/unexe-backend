<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CommitteeMember;
use App\Models\CommitteePage;
use App\Models\Partner;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicController extends Controller
{
    // Candidats validés (page publique)
    public function candidates(Request $request)
    {
        $query = Candidate::with('user', 'department')
            ->where('status', 'validated')
            ->whereNotNull('photo') // Seulement ceux qui ont complété leur profil
            ->orderBy('department_id')
            ->orderBy('year');

        // Filtre par département
        if ($request->has('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        // Filtre par année
        if ($request->has('year')) {
            $query->where('year', $request->year);
        }

        // Recherche par nom
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $candidates = $query->get()->map(function ($candidate) {
            return [
                'id'         => $candidate->id,
                'name'       => $candidate->user->name,
                'filiere'    => $candidate->filiere,
                'year'       => $candidate->year,
                'bio'        => $candidate->bio,
                'photo_url'  => $candidate->photo
                    ? Storage::url($candidate->photo)
                    : null,
                'department' => $candidate->department->name,
                'department_slug' => $candidate->department->slug,
            ];
        });

        // Grouper par département
        $grouped = $candidates->groupBy('department');

        // Départements disponibles pour les filtres
        $departments = Department::withCount([
            'candidates as validated_count' => fn($q) =>
                $q->where('status', 'validated')->whereNotNull('photo')
        ])->get();

        return response()->json([
            'candidates'  => $grouped,
            'departments' => $departments,
        ]);
    }

    // Page comité (publique)
    public function committee()
    {
        $page = CommitteePage::first();

        $members = CommitteeMember::with('user')
            ->orderBy('display_order')
            ->get()
            ->map(function ($member) {
                return [
                    'id'           => $member->id,
                    'name'         => $member->user->name,
                    'position'     => $member->position,
                    'bio'          => $member->bio,
                    'photo_url'    => $member->photo
                        ? Storage::url($member->photo)
                        : null,
                    'display_order' => $member->display_order,
                ];
            });

        return response()->json([
            'page'    => $page ? [
                'project_description' => $page->project_description,
                'vision'              => $page->vision,
                'objectives'          => $page->objectives,
                'team_photo_url'      => $page->team_photo
                    ? Storage::url($page->team_photo)
                    : null,
            ] : null,
            'members' => $members,
        ]);
    }

    // Partenaires (publique)
    public function partners()
    {
        $partners = Partner::orderBy('display_order')->get()->map(function ($p) {
            return [
                'id'           => $p->id,
                'name'         => $p->name,
                'logo_url'     => $p->logo ? Storage::url($p->logo) : null,
                'contribution' => $p->contribution,
                'website'      => $p->website,
            ];
        });

        return response()->json($partners);
    }
}