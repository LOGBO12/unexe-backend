<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RegistrationSetting;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class RegistrationController extends Controller
{
    /**
     * GET /api/registration-status  (public)
     */
    public function status()
    {
        $setting = RegistrationSetting::current();

        return response()->json([
            'is_open'               => $setting->isOpen(),
            'registration_open'     => $setting->registration_open,
            'registration_deadline' => $setting->registration_deadline
                ? $setting->registration_deadline->toIso8601String()
                : null,
            'closed_message'        => $setting->closed_message,
            'server_time'           => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/admin/registration-settings
     */
    public function show()
    {
        $setting = RegistrationSetting::current();

        return response()->json([
            'id'                    => $setting->id,
            'registration_open'     => (bool) $setting->registration_open,
            'registration_deadline' => $setting->registration_deadline
                ? $setting->registration_deadline->toIso8601String()
                : null,
            'closed_message'        => $setting->closed_message,
        ]);
    }

    /**
     * PUT /api/admin/registration-settings
     */
    public function update(Request $request)
    {
        Log::info('[Registration] Payload reçu', $request->all());

        $setting = RegistrationSetting::current();

        // ── registration_open ─────────────────────────────────────────────
        if ($request->has('registration_open')) {
            $setting->registration_open = filter_var(
                $request->input('registration_open'),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ) ?? true;
        }

        // ── registration_deadline ─────────────────────────────────────────
        if ($request->has('registration_deadline')) {
            $raw = $request->input('registration_deadline');

            if (empty($raw) || $raw === 'null') {
                $setting->registration_deadline = null;
            } else {
                try {
                    // Carbon accepte : ISO 8601, "2025-06-15T18:30", "2025-06-15 18:30:00", etc.
                    $deadline = Carbon::parse($raw);

                    // Sécurité : refuser une date passée
                    if ($deadline->isPast()) {
                        return response()->json([
                            'message' => 'La deadline doit être dans le futur.',
                            'errors'  => ['registration_deadline' => ['La date doit être dans le futur.']],
                        ], 422);
                    }

                    $setting->registration_deadline = $deadline;
                } catch (\Exception $e) {
                    Log::error('[Registration] Impossible de parser la date', [
                        'raw'   => $raw,
                        'error' => $e->getMessage(),
                    ]);

                    return response()->json([
                        'message' => 'Format de date invalide.',
                        'errors'  => ['registration_deadline' => ['Format invalide. Exemple attendu : 2025-06-15T18:30']],
                    ], 422);
                }
            }
        }

        // ── closed_message ────────────────────────────────────────────────
        if ($request->has('closed_message')) {
            $setting->closed_message = $request->input('closed_message') ?: null;
        }

        $setting->save();

        Log::info('[Registration] Sauvegardé', [
            'registration_open'     => $setting->registration_open,
            'registration_deadline' => $setting->registration_deadline?->toIso8601String(),
            'closed_message'        => $setting->closed_message,
        ]);

        return response()->json([
            'message' => 'Paramètres mis à jour avec succès.',
            'data'    => [
                'id'                    => $setting->id,
                'registration_open'     => (bool) $setting->registration_open,
                'registration_deadline' => $setting->registration_deadline
                    ? $setting->registration_deadline->toIso8601String()
                    : null,
                'closed_message'        => $setting->closed_message,
            ],
        ]);
    }

    /**
     * DELETE /api/admin/registration-settings/deadline
     */
    public function clearDeadline()
    {
        $setting = RegistrationSetting::current();
        $setting->registration_deadline = null;
        $setting->save();

        return response()->json(['message' => 'Deadline supprimée.']);
    }
}