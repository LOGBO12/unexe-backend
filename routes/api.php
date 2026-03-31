<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

// Controllers publics
use App\Http\Controllers\Public\PublicController;

// Controllers Auth
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\InvitationController;

// Controllers candidat
use App\Http\Controllers\Candidate\ApplicationController;
use App\Http\Controllers\Candidate\ProfileController;

// Controllers admin
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\CommitteeController;
use App\Http\Controllers\Admin\PartnerController;

// Controllers forum
use App\Http\Controllers\Community\ForumController;
use App\Http\Controllers\Admin\AdminProfileController;
use App\Http\Controllers\Admin\RegistrationController;
use App\Http\Controllers\Admin\CompetitionController;

// ===================================================
// ROUTES STATIQUES (fichiers) — avec CORS complet
// ===================================================

$corsHeaders = [
    'Access-Control-Allow-Origin'      => 'https://unexe2026.vercel.app',
    'Access-Control-Allow-Credentials' => 'true',
    'Access-Control-Allow-Methods'     => 'GET, OPTIONS',
    'Access-Control-Allow-Headers'     => 'Content-Type, Accept, Authorization, X-Requested-With',
    'Cache-Control'                    => 'public, max-age=86400',
];

// ─── Preflight OPTIONS ───
Route::options('/storage/{type}/{filename}', function () use ($corsHeaders) {
    return response('', 204)->withHeaders($corsHeaders);
});

// ─── Images partenaires ───
Route::get('/storage/partners/{filename}', function ($filename) use ($corsHeaders) {
    $path = storage_path('app/public/partners/' . $filename);
    if (!file_exists($path)) abort(404);
    
    foreach ($corsHeaders as $key => $value) {
        header($key . ': ' . $value);
    }
    
    return response()->file($path);
});

// ─── Avatars utilisateurs ───
Route::get('/storage/avatars/{filename}', function ($filename) use ($corsHeaders) {
    $path = storage_path('app/public/avatars/' . $filename);
    if (!file_exists($path)) abort(404);
    
    foreach ($corsHeaders as $key => $value) {
        header($key . ': ' . $value);
    }
    
    return response()->file($path);
});

// ===================================================
// ROUTES PUBLIQUES (sans authentification)
// ===================================================

// Preflight pour /profile (avant les routes protégées)
Route::options('/profile', function () {
    return response('', 204)->withHeaders([
        'Access-Control-Allow-Origin'      => 'https://unexe2026.vercel.app',
        'Access-Control-Allow-Credentials' => 'true',
        'Access-Control-Allow-Methods'     => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
        'Access-Control-Allow-Headers'     => 'Content-Type, Accept, Authorization, X-Requested-With',
    ]);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);
Route::get('/registration-status', [RegistrationController::class, 'status']);
Route::get('/leaderboard', [CompetitionController::class, 'publicLeaderboard']);

Route::get('/invitation/{token}',          [InvitationController::class, 'checkToken']);
Route::post('/invitation/{token}/activate', [InvitationController::class, 'activate']);

// Pages publiques
Route::prefix('public')->group(function () {
    Route::get('/candidates', [PublicController::class, 'candidates']);
    Route::get('/committee',  [PublicController::class, 'committee']);
    Route::get('/partners',   [PublicController::class, 'partners']);
    Route::get('/departments', [PublicController::class, 'departments']);
});

// Partenaires (lecture publique pour admin front)
Route::get('/partners', [PartnerController::class, 'index']);

// ===================================================
// ROUTES PROTÉGÉES (authentification requise)
// ===================================================
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    // ── PROFIL (tous les utilisateurs connectés) ──
    Route::get('/admin/profile',             [AdminProfileController::class, 'show']);
    Route::post('/admin/profile/update',     [AdminProfileController::class, 'update']);
    Route::put('/admin/profile/password',    [AdminProfileController::class, 'changePassword']);

    // -----------------------------------------------
    // CANDIDAT : Complétion profil
    // -----------------------------------------------
    Route::middleware('role:candidat')->group(function () {
        Route::post('/profile/complete', [ProfileController::class, 'complete']);
        Route::put('/profile',           [ProfileController::class, 'update']);
        Route::get('/profile',           [ProfileController::class, 'show']);

        // Candidature
        Route::post('/applications',  [ApplicationController::class, 'store']);
        Route::get('/my-application', [ApplicationController::class, 'myApplication']);
        Route::get('/my-scores', [CompetitionController::class, 'myScores']);
    });

    // -----------------------------------------------
    // FORUM (Comité + Candidats validés)
    // -----------------------------------------------
    Route::middleware('can_access_forum')->prefix('forum')->group(function () {
        // Topics
        Route::get('/topics',    [ForumController::class, 'index']);
        Route::get('/topics/{id}', [ForumController::class, 'show']);
        Route::post('/topics',   [ForumController::class, 'store']);
        Route::delete('/topics/{id}', [ForumController::class, 'destroy']);

        // Replies
        Route::post('/topics/{id}/replies', [ForumController::class, 'storeReply']);
        Route::delete('/replies/{id}',      [ForumController::class, 'destroyReply']);

        // Comité uniquement
        Route::middleware('role:super_admin,comite')->group(function () {
            Route::post('/announcements',        [ForumController::class, 'storeAnnouncement']);
            Route::put('/topics/{id}/pin',       [ForumController::class, 'pin']);
            Route::put('/topics/{id}/close',     [ForumController::class, 'close']);
            Route::put('/replies/{id}/official', [ForumController::class, 'markOfficial']);
        });
    });

    // -----------------------------------------------
    // SUPER ADMIN (paramètres inscription)
    // -----------------------------------------------
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/admin/registration-settings',              [RegistrationController::class, 'show']);
        Route::put('/admin/registration-settings',              [RegistrationController::class, 'update']);
        Route::delete('/admin/registration-settings/deadline',  [RegistrationController::class, 'clearDeadline']);
    });

    // -----------------------------------------------
    // COMITÉ & ADMIN
    // -----------------------------------------------
    Route::middleware('role:super_admin,comite')->group(function () {

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/logs',      [DashboardController::class, 'logs']);

        // Gestion candidatures
        Route::get('/applications',             [ApplicationController::class, 'index']);
        Route::get('/applications/{id}',        [ApplicationController::class, 'show']);
        Route::post('/applications/{id}/validate', [ApplicationController::class, 'validate']);
        Route::post('/applications/{id}/reject',   [ApplicationController::class, 'reject']);

        // Invitations
        Route::post('/invite',           [InvitationController::class, 'send']);
        Route::get('/invitations',       [InvitationController::class, 'index']);
        Route::delete('/invitations/{id}', [InvitationController::class, 'cancel']);
        
        // Comité — Membres
        Route::get('/committee/members',         [CommitteeController::class, 'index']);
        Route::post('/committee/members',        [CommitteeController::class, 'store']);
        Route::put('/committee/members/{id}',    [CommitteeController::class, 'update']);
        Route::delete('/committee/members/{id}', [CommitteeController::class, 'destroy']);
        Route::get('/committee/available-users', [CommitteeController::class, 'availableUsers']);

        // Comité — Page publique
        Route::get('/committee/page', [CommitteeController::class, 'getPage']);
        Route::put('/committee/page', [CommitteeController::class, 'updatePage']);

        // Partenaires
        Route::post('/partners',       [PartnerController::class, 'store']);
        Route::put('/partners/{id}',   [PartnerController::class, 'update']);
        Route::delete('/partners/{id}', [PartnerController::class, 'destroy']);

        // Compétition
        Route::prefix('competition')->group(function () {
            Route::get('/phases',                 [CompetitionController::class, 'phases']);
            Route::post('/setup',                 [CompetitionController::class, 'setup']);
            Route::delete('/reset',               [CompetitionController::class, 'reset']);        
            Route::put('/phases/{id}/activate',   [CompetitionController::class, 'activatePhase']);
            Route::put('/phases/{id}/complete',   [CompetitionController::class, 'completePhase']);
            Route::get('/phases/{id}/candidates', [CompetitionController::class, 'phaseCandidates']);
            Route::post('/scores/{scoreId}',      [CompetitionController::class, 'gradeCandidate']);
        });
    });
});