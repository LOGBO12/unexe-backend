<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\CommitteeMember;
use App\Models\CommitteePage;
use App\Models\Partner;
use Illuminate\Support\Facades\Log;

class PublicController extends Controller
{
    /**
     * GET /api/public/candidates
     */
    public function candidates()
    {
        try {
            $candidates = \App\Models\User::where('role', 'candidat')
                ->whereHas('candidate', fn($q) => $q->where('status', 'validated'))
                ->with('candidate')
                ->get()
                ->map(fn($u) => [
                    'id'         => $u->id,
                    'name'       => $u->name,
                    'photo_url'  => $u->avatar ? asset('storage/' . $u->avatar) : null,
                    'department' => $u->candidate?->department,
                    'year'       => $u->candidate?->year,
                    'filiere'    => $u->candidate?->filiere,
                    'bio'        => $u->candidate?->bio,
                ]);

            return response()->json($candidates);
        } catch (\Exception $e) {
            Log::error('[Public/candidates] ' . $e->getMessage());
            return response()->json([]);
        }
    }

    /**
     * GET /api/public/committee
     *
     * Retourne { page: {...}, members: [...] }
     * Gère le cas où CommitteePage est vide (table vide = null, pas d'erreur)
     */
    public function committee()
    {
        try {
            // ── Page publique du comité ───────────────────────────────────
            $page     = CommitteePage::first();
            $pageData = null;

            if ($page) {
                $pageData = [
                    'id'                  => $page->id,
                    'project_description' => $page->project_description,
                    'vision'              => $page->vision,
                    // objectives est casté en array dans le modèle via $casts
                    'objectives'          => $page->objectives ?? [],
                    'team_photo'          => $page->team_photo,
                    // URL absolue — asset() génère https://domain.com/storage/...
                    'team_photo_url'      => $page->team_photo
                        ? asset('storage/' . $page->team_photo)
                        : null,
                ];
            }

            // ── Membres du comité ─────────────────────────────────────────
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

            return response()->json([
                'page'    => $pageData,
                'members' => $members,
            ]);

        } catch (\Exception $e) {
            Log::error('[Public/committee] ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine());

            // Retourner le message d'erreur pour faciliter le debug
            // (à retirer en production)
            return response()->json([
                'page'    => null,
                'members' => [],
                '_debug'  => $e->getMessage(),
            ], 500);
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
                    'id'            => $p->id,
                    'name'          => $p->name,
                    'website'       => $p->website,
                    'display_order' => $p->display_order,
                    'logo'          => $p->logo,
                    'logo_url'      => $p->logo
                        ? asset('storage/' . $p->logo)
                        : null,
                ]);

            return response()->json($partners);
        } catch (\Exception $e) {
            Log::error('[Public/partners] ' . $e->getMessage());
            return response()->json([]);
        }
    }
}