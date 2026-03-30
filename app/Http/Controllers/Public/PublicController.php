<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\CommitteeMember;
use App\Models\CommitteePage;
use App\Models\Partner;
use App\Models\CompetitionPhase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PublicController extends Controller
{
    public function candidates(Request $request)
    {
        try {
            $search       = $request->query('search');
            $departmentId = $request->query('department_id');
            $year         = $request->query('year');

            $phases = CompetitionPhase::whereIn('status', ['active', 'completed'])
                ->orderBy('phase_number')
                ->get(['id', 'phase_number', 'name', 'status', 'is_final']);

            $hasCompetition = $phases->isNotEmpty();
            $phaseIds       = $phases->pluck('id');

            $users = \App\Models\User::where('role', 'candidat')
                ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
                ->whereHas('candidate', fn($q) => $q
                    ->where('status', 'validated')
                    ->where('is_visible', true)
                    ->when($departmentId, fn($q) => $q->where('department_id', $departmentId))
                    ->when($year,         fn($q) => $q->where('year', $year))
                )
                ->with([
                    'candidate',
                    'candidate.department',
                    'candidate.scores' => fn($q) => $q->whereIn('phase_id', $phaseIds),
                ])
                ->get();

            $grouped = [];

            foreach ($users as $u) {
                $candidate = $u->candidate;
                if (!$candidate) continue;

                $deptName = $candidate->department?->name ?? 'Autre';

                $phaseScores = $phases->map(function ($phase) use ($candidate) {
                    $scoreEntry = $candidate->scores->firstWhere('phase_id', $phase->id);

                    if (!$scoreEntry) return null;

                    $showScore = ($phase->status === 'completed') && ($scoreEntry->score !== null);

                    return [
                        'phase_number' => $phase->phase_number,
                        'phase_name'   => $phase->name,
                        'score'        => $showScore ? (float) $scoreEntry->score : null,
                        'status'       => $scoreEntry->status,
                        'is_final'     => (bool) $phase->is_final,
                        'phase_status' => $phase->status,
                    ];
                })
                ->filter()
                ->values();

                $grouped[$deptName][] = [
                    'id'              => $u->id,
                    'candidate_id'    => $candidate->id,
                    'name'            => $u->name,
                    'photo_url' => $u->avatar 
                    ? url('/api/storage/avatars/' . basename($u->avatar)) 
                    : null,
                    'department'      => $deptName,
                    'department_slug' => $candidate->department?->slug ?? '',
                    'year'            => $candidate->year,
                    'filiere'         => $candidate->filiere,
                    'bio'             => $candidate->bio,
                    'is_leader'       => (bool) ($candidate->is_leader ?? false),
                    'current_phase'   => $candidate->current_phase ?? 1,
                    'phase_scores'    => $phaseScores,
                ];
            }

            foreach ($grouped as &$candidates) {
                usort($candidates, function ($a, $b) {
                    if ($a['is_leader'] && !$b['is_leader']) return -1;
                    if (!$a['is_leader'] && $b['is_leader']) return 1;
                    return ($b['current_phase'] ?? 1) - ($a['current_phase'] ?? 1);
                });
            }
            unset($candidates);

            return response()->json([
                'candidates'      => $grouped,
                'phases'          => $phases,
                'has_competition' => $hasCompetition,
            ]);

        } catch (\Exception $e) {
            Log::error('[Public/candidates] ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'candidates'      => [],
                'phases'          => [],
                'has_competition' => false,
            ]);
        }
    }

    public function departments()
    {
        try {
            $departments = \App\Models\Department::orderBy('name')->get(['id', 'name', 'slug']);
            return response()->json(['departments' => $departments]);
        } catch (\Exception $e) {
            return response()->json(['departments' => []]);
        }
    }

    public function committee()
    {
        try {
            $page     = CommitteePage::first();
            $pageData = null;

            if ($page) {
                $pageData = [
                    'id'                  => $page->id,
                    'project_description' => $page->project_description,
                    'vision'              => $page->vision,
                    'objectives'          => $page->objectives ?? [],
                    'team_photo_url'      => $page->team_photo
                        ? asset('storage/' . $page->team_photo)
                        : null,
                ];
            }

            $members = CommitteeMember::with('user')
                ->orderBy('display_order')
                ->get()
                ->map(fn($m) => [
                    'id'        => $m->id,
                    'name'      => $m->user?->name ?? 'Membre',
                    'position'  => $m->position,
                    'bio'       => $m->bio,
                   'photo_url' => $m->photo
    ? url('/api/storage/avatars/' . basename($m->photo))
    : ($m->user?->avatar
        ? url('/api/storage/avatars/' . basename($m->user->avatar))
        : null),
                ]);

            return response()->json(['page' => $pageData, 'members' => $members]);

        } catch (\Exception $e) {
            Log::error('[Public/committee] ' . $e->getMessage());
            return response()->json(['page' => null, 'members' => []], 500);
        }
    }

    public function partners()
    {
        try {
            $partners = Partner::orderBy('display_order')
                ->get()
                ->map(fn($p) => [
                    'id'       => $p->id,
                    'name'     => $p->name,
                    'website'  => $p->website,
                   'logo_url' => $p->logo ? url('/api/storage/partners/' . basename($p->logo)) : null,

                ]);

            return response()->json($partners);
        } catch (\Exception $e) {
            Log::error('[Public/partners] ' . $e->getMessage());
            return response()->json([]);
        }
    }
}