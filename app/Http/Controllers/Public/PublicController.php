<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\CommitteeMember;
use App\Models\CommitteePage;
use App\Models\Partner;
use App\Models\CompetitionPhase;
use Illuminate\Support\Facades\Log;

class PublicController extends Controller
{
    /**
     * GET /api/public/candidates
     *
     * Retourne uniquement les candidats visibles (is_visible = true),
     * groupés par département, avec leurs scores par phase.
     *
     * Règles :
     * - Candidat éliminé (is_visible = false) → exclu totalement
     * - Phase "completed" → score visible publiquement
     * - Phase "active"    → score masqué (null), mais statut visible
     */
    public function candidates()
    {
        try {
            // 1. Phases actives ou terminées uniquement (pas les "pending")
            $phases = CompetitionPhase::whereIn('status', ['active', 'completed'])
                ->orderBy('phase_number')
                ->get(['id', 'phase_number', 'name', 'status', 'is_final']);

            $hasCompetition = $phases->isNotEmpty();
            $phaseIds       = $phases->pluck('id');

            // 2. Candidats validés ET visibles (is_visible = true exclut les éliminés)
            $users = \App\Models\User::where('role', 'candidat')
                ->whereHas('candidate', fn($q) => $q
                    ->where('status', 'validated')
                    ->where('is_visible', true)
                )
                ->with([
                    'candidate',
                    'candidate.department',
                    // Charger uniquement les scores des phases publiques
                    'candidate.scores' => fn($q) => $q->whereIn('phase_id', $phaseIds),
                ])
                ->get();

            // 3. Construire la réponse groupée
            $grouped = [];

            foreach ($users as $u) {
                $candidate = $u->candidate;
                if (!$candidate) continue;

                $deptName = $candidate->department?->name ?? 'Autre';

                // ── Construire phase_scores ──
                // Même structure que /my-scores :
                // { phase_number, phase_name, score, status, is_final, phase_status }
                $phaseScores = $phases->map(function ($phase) use ($candidate) {
                    // Chercher le score de ce candidat pour cette phase
                    $scoreEntry = $candidate->scores
                        ->firstWhere('phase_id', $phase->id);

                    // Candidat absent de cette phase → on skip
                    if (!$scoreEntry) return null;

                    // Score visible uniquement si la phase est terminée
                    $showScore = ($phase->status === 'completed')
                        && ($scoreEntry->score !== null);

                    return [
                        'phase_number' => $phase->phase_number,
                        'phase_name'   => $phase->name,
                        'score'        => $showScore ? (float) $scoreEntry->score : null,
                        'status'       => $scoreEntry->status,  // pending|continuing|eliminated|leader
                        'is_final'     => (bool) $phase->is_final,
                        'phase_status' => $phase->status,        // active|completed
                    ];
                })
                ->filter()   // retirer les null
                ->values();

                $grouped[$deptName][] = [
                    'id'              => $u->id,
                    'candidate_id'    => $candidate->id,
                    'name'            => $u->name,
                    'photo_url'       => $u->avatar
                        ? asset('storage/' . $u->avatar)
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

            // 4. Tri : leaders en premier, puis phase décroissante
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

    // ─────────────────────────────────────────────────────────────────────────

    public function departments()
    {
        try {
            $departments = \App\Models\Department::orderBy('name')->get(['id', 'name', 'slug']);
            return response()->json(['departments' => $departments]);
        } catch (\Exception $e) {
            return response()->json(['departments' => []]);
        }
    }

    /**
     * GET /api/public/committee
     */
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
                        ? asset('storage/' . $m->photo)
                        : ($m->user?->avatar
                            ? asset('storage/' . $m->user->avatar)
                            : null),
                ]);

            return response()->json(['page' => $pageData, 'members' => $members]);

        } catch (\Exception $e) {
            Log::error('[Public/committee] ' . $e->getMessage());
            return response()->json(['page' => null, 'members' => []], 500);
        }
    }

    /**
     * GET /api/public/partners
     */
    public function partners()
    {
        try {
            $partners = Partner::orderBy('display_order')
                ->get()
                ->map(fn($p) => [
                    'id'       => $p->id,
                    'name'     => $p->name,
                    'website'  => $p->website,
                    'logo_url' => $p->logo ? asset('storage/' . $p->logo) : null,
                ]);

            return response()->json($partners);
        } catch (\Exception $e) {
            Log::error('[Public/partners] ' . $e->getMessage());
            return response()->json([]);
        }
    }
}