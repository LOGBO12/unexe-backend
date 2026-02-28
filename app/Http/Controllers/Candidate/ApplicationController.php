<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\ActionLog;
use App\Mail\CandidateValidated;
use App\Mail\CandidateRejected;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ApplicationController extends Controller
{
    // ==================== CANDIDAT ====================

    // Soumettre un dossier de candidature
    public function store(Request $request)
    {
        $user = $request->user();

        // Vérifier qu'il n'a pas déjà soumis
        if (Application::where('user_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'Vous avez déjà soumis un dossier de candidature.'
            ], 422);
        }

        $data = $request->validate([
            'department_id'     => 'required|exists:departments,id',
            'filiere'           => 'required|string|max:255',
            'year'              => 'required|in:1,2',
            'matricule'         => 'required|string|max:50',
            'phone'             => 'required|string|max:20',
            'motivation_letter' => 'required|string|min:100|max:2000',
            'documents'         => 'nullable|array',
            'documents.cv'      => 'nullable|file|mimes:pdf|max:2048',
            'documents.notes'   => 'nullable|file|mimes:pdf|max:2048',
            'documents.photo'   => 'nullable|file|mimes:jpg,jpeg,png|max:1024',
        ]);

        // Upload des documents
        $uploadedDocs = [];

        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $key => $file) {
                $path = $file->store("applications/{$user->id}", 'public');
                $uploadedDocs[$key] = $path;
            }
        }

        $application = Application::create([
            'user_id'           => $user->id,
            'department_id'     => $data['department_id'],
            'filiere'           => $data['filiere'],
            'year'              => $data['year'],
            'matricule'         => $data['matricule'],
            'phone'             => $data['phone'],
            'motivation_letter' => $data['motivation_letter'],
            'documents'         => $uploadedDocs,
            'status'            => 'pending',
        ]);

        return response()->json([
            'message'     => 'Votre dossier a été soumis avec succès. Le comité va l\'examiner.',
            'application' => $application->load('department'),
        ], 201);
    }

    // Voir sa propre candidature
    public function myApplication(Request $request)
    {
        $application = Application::where('user_id', $request->user()->id)
            ->with('department', 'reviewedBy')
            ->first();

        if (!$application) {
            return response()->json([
                'message' => 'Aucun dossier soumis.',
                'application' => null,
            ]);
        }

        return response()->json($application);
    }

    // ==================== COMITÉ ====================

    // Liste de toutes les candidatures (avec filtres)
    public function index(Request $request)
    {
        $query = Application::with('user', 'department', 'reviewedBy')
            ->orderBy('created_at', 'desc');

        // Filtres
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->has('year')) {
            $query->where('year', $request->year);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $applications = $query->paginate(20);

        // Stats rapides en en-tête
        $stats = [
            'total'     => Application::count(),
            'pending'   => Application::where('status', 'pending')->count(),
            'validated' => Application::where('status', 'validated')->count(),
            'rejected'  => Application::where('status', 'rejected')->count(),
        ];

        return response()->json([
            'applications' => $applications,
            'stats'        => $stats,
        ]);
    }

    // Voir un dossier en détail
    public function show(int $id)
    {
        $application = Application::with(
            'user',
            'department',
            'reviewedBy'
        )->findOrFail($id);

        // Ajouter les URLs des documents
        if ($application->documents) {
            $docs = [];
            foreach ($application->documents as $key => $path) {
                $docs[$key] = [
                    'path' => $path,
                    'url'  => Storage::url($path),
                ];
            }
            $application->documents_urls = $docs;
        }

        return response()->json($application);
    }

    // Valider une candidature
    public function validate(Request $request, int $id)
    {
        $application = Application::with('user', 'department')->findOrFail($id);

        if ($application->status !== 'pending') {
            return response()->json([
                'message' => 'Ce dossier a déjà été traité.'
            ], 422);
        }

        $data = $request->validate([
            'review_note' => 'nullable|string|max:500',
            'filiere'     => 'nullable|string|max:255',
            'year'        => 'nullable|in:1,2',
        ]);

        // Mettre à jour la candidature
        $application->update([
            'status'      => 'validated',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => Carbon::now(),
            'review_note' => $data['review_note'] ?? null,
        ]);

        // Créer le profil candidat
        Candidate::updateOrCreate(
            ['user_id' => $application->user_id],
            [
                'department_id' => $application->department_id,
                'filiere'       => $data['filiere'] ?? $application->filiere,
                'year'          => $data['year'] ?? $application->year,
                'matricule'     => $application->matricule,
                'phone'         => $application->phone,
                'status'        => 'validated',
                'validated_by'  => $request->user()->id,
                'validated_at'  => Carbon::now(),
            ]
        );

        // Envoyer l'email de notification
        Mail::to($application->user->email)->send(
            new CandidateValidated($application)
        );

        // Logger l'action (traçabilité)
        ActionLog::log(
            $request->user(),
            'validate_candidate',
            $application,
            [
                'candidate_name'  => $application->user->name,
                'candidate_email' => $application->user->email,
                'department'      => $application->department->name,
                'review_note'     => $data['review_note'] ?? null,
            ]
        );

        return response()->json([
            'message'     => "Candidature de {$application->user->name} validée avec succès.",
            'application' => $application->fresh()->load('user', 'department', 'reviewedBy'),
        ]);
    }

    // Rejeter une candidature
    public function reject(Request $request, int $id)
    {
        $application = Application::with('user', 'department')->findOrFail($id);

        if ($application->status !== 'pending') {
            return response()->json([
                'message' => 'Ce dossier a déjà été traité.'
            ], 422);
        }

        $data = $request->validate([
            'review_note' => 'required|string|max:500',
        ]);

        $application->update([
            'status'      => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => Carbon::now(),
            'review_note' => $data['review_note'],
        ]);

        // Envoyer l'email de rejet
        Mail::to($application->user->email)->send(
            new CandidateRejected($application)
        );

        // Logger l'action
        ActionLog::log(
            $request->user(),
            'reject_candidate',
            $application,
            [
                'candidate_name'  => $application->user->name,
                'candidate_email' => $application->user->email,
                'department'      => $application->department->name,
                'reason'          => $data['review_note'],
            ]
        );

        return response()->json([
            'message'     => "Candidature de {$application->user->name} rejetée.",
            'application' => $application->fresh()->load('user', 'department', 'reviewedBy'),
        ]);
    }
}