<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompetitionPhase;
use App\Models\CandidateScore;
use App\Models\Candidate;
use App\Models\ActionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CompetitionController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────────
    // PHASES
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /admin/competition/phases
     */
    public function phases()
    {
        $phases = CompetitionPhase::withCount('scores')
            ->orderBy('phase_number')
            ->get()
            ->map(function ($phase) {
                $scored     = $phase->scores()->whereNotNull('score')->count();
                $continuing = $phase->scores()->where('status', 'continuing')->count();
                $eliminated = $phase->scores()->where('status', 'eliminated')->count();
                $leaders    = $phase->scores()->where('status', 'leader')->count();

                return array_merge($phase->toArray(), [
                    'scored_count'     => $scored,
                    'continuing_count' => $continuing,
                    'eliminated_count' => $eliminated,
                    'leaders_count'    => $leaders,
                ]);
            });

        // Le concours peut être réinitialisé seulement si toutes les phases sont "pending"
        $canReset = $phases->count() > 0
            && $phases->every(fn($p) => $p['status'] === 'pending');

        return response()->json([
            'phases'        => $phases,
            'has_phases'    => $phases->count() > 0,
            'active_phase'  => CompetitionPhase::where('status', 'active')->first(),
            'can_reset'     => $canReset,
        ]);
    }

    /**
     * POST /admin/competition/setup
     * Créer les phases. Si des phases "pending" existent déjà, elles sont d'abord supprimées.
     */
    public function setup(Request $request)
{
    $lockedPhases = CompetitionPhase::whereIn('status', ['active', 'completed'])->count();
    if ($lockedPhases > 0) {
        return response()->json([
            'message' => 'Impossible de reconfigurer : une ou plusieurs phases sont déjà actives ou terminées.'
        ], 422);
    }

    $data = $request->validate([
        'total_phases'          => 'required|integer|min:1|max:10',
        'phases'                => 'required|array',
        'phases.*.name'         => 'required|string|max:255',
        'phases.*.description'  => 'nullable|string|max:500',
    ]);

    if (count($data['phases']) !== $data['total_phases']) {
        return response()->json([
            'message' => 'Le nombre de phases définies doit correspondre au total.'
        ], 422);
    }

    try {
        DB::transaction(function () use ($data, $request) {
    // 1. Récupérer les IDs des phases pending
    $pendingPhaseIds = CompetitionPhase::where('status', 'pending')
        ->pluck('id');

    // 2. Supprimer les scores liés à ces phases d'abord
    if ($pendingPhaseIds->isNotEmpty()) {
        CandidateScore::whereIn('phase_id', $pendingPhaseIds)->delete();
    }

    // 3. Maintenant supprimer les phases
    CompetitionPhase::whereIn('id', $pendingPhaseIds)->delete();

    // 4. Recréer les phases
    foreach ($data['phases'] as $i => $phaseData) {
        CompetitionPhase::create([
            'phase_number' => $i + 1,
            'name'         => $phaseData['name'],
            'description'  => $phaseData['description'] ?? null,
            'total_phases' => $data['total_phases'],
            'status'       => 'pending',
            'is_final'     => ($i + 1) === $data['total_phases'],
            'created_by'   => $request->user()->id,
        ]);
    }
});
    } catch (\Exception $e) {
        \Log::error('[Competition setup] ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json([
            'message' => 'Erreur serveur : ' . $e->getMessage()
        ], 500);
    }

    $created = CompetitionPhase::orderBy('phase_number')->get();

    ActionLog::log($request->user(), 'setup_competition', null, [
        'total_phases' => $data['total_phases'],
    ]);

    return response()->json([
        'message' => 'Concours configuré avec ' . $data['total_phases'] . ' phase(s).',
        'phases'  => $created,
    ], 201);
}

    /**
     * DELETE /admin/competition/reset
     * Réinitialiser entièrement le concours (uniquement si toutes les phases sont "pending")
     */
    public function reset(Request $request)
{
    $lockedPhases = CompetitionPhase::whereIn('status', ['active', 'completed'])->count();
    if ($lockedPhases > 0) {
        return response()->json([
            'message' => 'Impossible de réinitialiser : une ou plusieurs phases sont actives ou terminées.'
        ], 422);
    }

    $pendingPhaseIds = CompetitionPhase::where('status', 'pending')->pluck('id');

    if ($pendingPhaseIds->isNotEmpty()) {
        CandidateScore::whereIn('phase_id', $pendingPhaseIds)->delete();
        CompetitionPhase::whereIn('id', $pendingPhaseIds)->delete();
    }

    ActionLog::log($request->user(), 'reset_competition', null, []);

    return response()->json(['message' => 'Concours réinitialisé. Vous pouvez reconfigurer les phases.']);
}

    /**
     * PUT /admin/competition/phases/{id}/activate
     */
    public function activatePhase(Request $request, int $id)
    {
        $phase = CompetitionPhase::findOrFail($id);

        if ($phase->status === 'active') {
            return response()->json(['message' => 'Cette phase est déjà active.'], 422);
        }

        if ($phase->status === 'completed') {
            return response()->json(['message' => 'Cette phase est déjà terminée.'], 422);
        }

        if ($phase->phase_number > 1) {
            $prev = CompetitionPhase::where('phase_number', $phase->phase_number - 1)->first();
            if ($prev && $prev->status !== 'completed') {
                return response()->json([
                    'message' => 'La phase précédente doit être complétée avant d\'activer celle-ci.'
                ], 422);
            }
        }

        CompetitionPhase::where('status', 'active')->update(['status' => 'completed']);

        $phase->update(['status' => 'active']);

        $candidates = $this->getEligibleCandidates($phase);
        foreach ($candidates as $candidate) {
            CandidateScore::firstOrCreate([
                'candidate_id' => $candidate->id,
                'phase_id'     => $phase->id,
            ], [
                'status' => 'pending',
            ]);
        }

        ActionLog::log($request->user(), 'activate_phase', $phase, [
            'phase_number'   => $phase->phase_number,
            'eligible_count' => $candidates->count(),
        ]);

        return response()->json([
            'message'        => "Phase {$phase->phase_number} activée.",
            'phase'          => $phase->fresh(),
            'eligible_count' => $candidates->count(),
        ]);
    }

    /**
     * PUT /admin/competition/phases/{id}/complete
     */
    public function completePhase(Request $request, int $id)
    {
        $phase = CompetitionPhase::findOrFail($id);

        if ($phase->status !== 'active') {
            return response()->json(['message' => 'Seule une phase active peut être clôturée.'], 422);
        }

        $pendingScores = CandidateScore::where('phase_id', $phase->id)
            ->where('status', 'pending')
            ->with('candidate')
            ->get();

        foreach ($pendingScores as $score) {
            $score->update([
                'status'    => 'eliminated',
                'comment'   => 'Éliminé automatiquement (non noté avant clôture)',
                'graded_by' => $request->user()->id,
                'graded_at' => now(),
            ]);
            $score->candidate?->update(['is_visible' => false]);
        }

        $phase->update(['status' => 'completed']);

        $survivors = CandidateScore::where('phase_id', $phase->id)->where('status', 'continuing')->count();
        $leaders   = CandidateScore::where('phase_id', $phase->id)->where('status', 'leader')->count();

        ActionLog::log($request->user(), 'complete_phase', $phase, [
            'phase_number'    => $phase->phase_number,
            'auto_eliminated' => $pendingScores->count(),
            'survivors'       => $survivors,
            'leaders'         => $leaders,
        ]);

        return response()->json([
            'message'         => "Phase {$phase->phase_number} clôturée.",
            'phase'           => $phase->fresh(),
            'auto_eliminated' => $pendingScores->count(),
            'survivors'       => $survivors,
            'leaders'         => $leaders,
        ]);
    }

   
    public function phaseCandidates(Request $request, int $id)
    {
        $phase = CompetitionPhase::findOrFail($id);

        $scores = CandidateScore::where('phase_id', $phase->id)
            ->with(['candidate.user', 'candidate.department', 'gradedBy'])
            ->get()
            ->map(function ($score) use ($phase) {
                $c = $score->candidate;
                return [
                    'score_id'        => $score->id,
                    'candidate_id'    => $c->id,
                    'name'            => $c->user?->name,
                    'email'           => $c->user?->email,
                    'photo_url'       => $c->user?->avatar ? asset('storage/' . $c->user->avatar) : null,
                    'department'      => $c->department?->name,
                    'department_slug' => $c->department?->slug,
                    'filiere'         => $c->filiere,
                    'year'            => $c->year,
                    'score'           => $score->score,
                    'status'          => $score->status,
                    'comment'         => $score->comment,
                    'graded_by_name'  => $score->gradedBy?->name,
                    'graded_at'       => $score->graded_at,
                    'current_phase'   => $c->current_phase,
                    'is_leader'       => $c->is_leader,
                ];
            })
            ->sortByDesc('score')
            ->values();

        $stats = [
            'total'      => $scores->count(),
            'graded'     => $scores->where('score', '!=', null)->count(),
            'pending'    => $scores->where('status', 'pending')->count(),
            'continuing' => $scores->where('status', 'continuing')->count(),
            'eliminated' => $scores->where('status', 'eliminated')->count(),
            'leaders'    => $scores->where('status', 'leader')->count(),
            'avg_score'  => $scores->whereNotNull('score')->avg('score'),
            'max_score'  => $scores->whereNotNull('score')->max('score'),
        ];

        return response()->json([
            'phase'  => $phase,
            'scores' => $scores,
            'stats'  => $stats,
        ]);
    }

    /**
     * POST /admin/competition/scores/{scoreId}
     */
    public function gradeCandidate(Request $request, int $scoreId)
    {
        $scoreEntry = CandidateScore::with(['candidate', 'phase'])->findOrFail($scoreId);

        $phase = $scoreEntry->phase;
        if ($phase->status !== 'active') {
            return response()->json([
                'message' => 'Cette phase n\'est plus active. Impossible de noter.'
            ], 422);
        }

        $data = $request->validate([
            'score'   => 'required|numeric|min:0|max:20',
            'status'  => ['required', 'in:continuing,eliminated,leader'],
            'comment' => 'nullable|string|max:500',
        ]);

        if ($data['status'] === 'leader' && !$phase->is_final) {
            return response()->json([
                'message' => 'Le statut "leader" ne peut être attribué que sur la phase finale.'
            ], 422);
        }

        if ($data['status'] === 'continuing' && $phase->is_final) {
            return response()->json([
                'message' => 'Sur la phase finale, le statut doit être "leader" ou "eliminated".'
            ], 422);
        }

        $candidate = $scoreEntry->candidate;

        $scoreEntry->update([
            'score'     => $data['score'],
            'status'    => $data['status'],
            'comment'   => $data['comment'] ?? null,
            'graded_by' => $request->user()->id,
            'graded_at' => Carbon::now(),
        ]);

        if ($data['status'] === 'eliminated') {
            $candidate->update(['is_visible' => false, 'current_phase' => $phase->phase_number]);
        } elseif ($data['status'] === 'continuing') {
            $candidate->update(['current_phase' => $phase->phase_number + 1, 'is_visible' => true]);
        } elseif ($data['status'] === 'leader') {
            $candidate->update(['is_leader' => true, 'is_visible' => true, 'current_phase' => $phase->phase_number]);
        }

        ActionLog::log($request->user(), 'grade_candidate', $candidate, [
            'phase'     => $phase->phase_number,
            'score'     => $data['score'],
            'status'    => $data['status'],
            'candidate' => $candidate->user?->name,
        ]);

        return response()->json([
            'message'     => 'Note enregistrée.',
            'score_entry' => $scoreEntry->fresh()->load('gradedBy'),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // RÉSULTATS PUBLICS
    // ──────────────────────────────────────────────────────────────────────────

    public function publicLeaderboard()
    {
        $finalPhase = CompetitionPhase::where('is_final', true)->where('status', 'completed')->first();

        if (!$finalPhase) {
            return response()->json([
                'available' => false,
                'message'   => 'Le classement final n\'est pas encore disponible.',
                'leaders'   => [],
                'all'       => [],
            ]);
        }

        $phases = CompetitionPhase::where('status', 'completed')->orderBy('phase_number')->get();

        $candidates = Candidate::with(['user', 'department', 'scores.phase'])
            ->whereHas('user')
            ->get()
            ->map(function ($c) use ($phases) {
                $phaseScores = $phases->map(function ($phase) use ($c) {
                    $score = $c->scores->firstWhere('phase_id', $phase->id);
                    return [
                        'phase_number' => $phase->phase_number,
                        'phase_name'   => $phase->name,
                        'score'        => $score?->score,
                        'status'       => $score?->status,
                        'comment'      => $score?->comment,
                    ];
                });

                $finalScore = $c->scores->firstWhere('phase_id', $phases->last()?->id);

                return [
                    'id'              => $c->id,
                    'name'            => $c->user?->name,
                    'photo_url'       => $c->user?->avatar ? asset('storage/' . $c->user->avatar) : null,
                    'department'      => $c->department?->name,
                    'department_slug' => $c->department?->slug,
                    'filiere'         => $c->filiere,
                    'year'            => $c->year,
                    'bio'             => $c->bio,
                    'is_leader'       => $c->is_leader,
                    'current_phase'   => $c->current_phase,
                    'phase_scores'    => $phaseScores,
                    'final_status'    => $finalScore?->status,
                ];
            })
            ->sortByDesc(fn($c) => $c['phase_scores']->last()['score'] ?? -1)
            ->values();

        return response()->json([
            'available' => true,
            'phases'    => $phases,
            'leaders'   => $candidates->where('is_leader', true)->values(),
            'all'       => $candidates,
        ]);
    }

    public function myScores(Request $request)
    {
        $user      = $request->user();
        $candidate = $user->candidate;

        if (!$candidate) {
            return response()->json(['message' => 'Aucun profil candidat trouvé.'], 404);
        }

        $scores = CandidateScore::where('candidate_id', $candidate->id)
            ->with('phase')
            ->orderBy('phase_id')
            ->get()
            ->map(function ($s) use ($candidate) {
                $rank  = null;
                $total = null;
                if ($s->score !== null) {
                    $rank  = CandidateScore::where('phase_id', $s->phase_id)->whereNotNull('score')->where('score', '>', $s->score)->count() + 1;
                    $total = CandidateScore::where('phase_id', $s->phase_id)->whereNotNull('score')->count();
                }

                return [
                    'phase_number'  => $s->phase?->phase_number,
                    'phase_name'    => $s->phase?->name,
                    'score'         => $s->score,
                    'status'        => $s->status,
                    'comment'       => $s->comment,
                    'rank'          => $rank,
                    'total_graded'  => $total,
                    'graded_at'     => $s->graded_at,
                ];
            });

        $activePhase = CompetitionPhase::where('status', 'active')->first();

        return response()->json([
            'candidate'    => [
                'name'          => $user->name,
                'current_phase' => $candidate->current_phase,
                'is_leader'     => $candidate->is_leader,
                'is_visible'    => $candidate->is_visible,
            ],
            'scores'       => $scores,
            'active_phase' => $activePhase,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HELPER
    // ──────────────────────────────────────────────────────────────────────────

    private function getEligibleCandidates(CompetitionPhase $phase)
    {
        if ($phase->phase_number === 1) {
            return Candidate::where('status', 'validated')->where('is_visible', true)->get();
        }

        $prevPhase = CompetitionPhase::where('phase_number', $phase->phase_number - 1)->first();
        if (!$prevPhase) return collect();

        $continuingIds = CandidateScore::where('phase_id', $prevPhase->id)
            ->where('status', 'continuing')
            ->pluck('candidate_id');

        return Candidate::whereIn('id', $continuingIds)->get();
    }
}