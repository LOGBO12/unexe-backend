<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\User;
use App\Models\Department;
use App\Models\ActionLog;
use App\Models\Invitation;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    // Stats du tableau de bord
    public function index()
    {
        // Stats générales
        $stats = [
            'candidates' => [
                'total'     => Candidate::count(),
                'validated' => Candidate::validated()->count(),
                'pending'   => Candidate::pending()->count(),
                'rejected'  => Candidate::rejected()->count(),
                'year1'     => Candidate::validated()->year1()->count(),
                'year2'     => Candidate::validated()->year2()->count(),
            ],
            'applications' => [
                'total'     => Application::count(),
                'pending'   => Application::pending()->count(),
                'validated' => Application::validated()->count(),
                'rejected'  => Application::rejected()->count(),
            ],
            'members' => [
                'total' => User::whereIn('role', ['comite', 'super_admin'])->count(),
            ],
            'invitations' => [
                'total'   => Invitation::count(),
                'pending' => Invitation::whereNull('used_at')
                                ->where('expires_at', '>', now())
                                ->count(),
            ],
        ];

        // Stats par département
        $byDepartment = Department::withCount([
            'candidates as candidates_total',
            'candidates as candidates_validated' => fn($q) => $q->where('status', 'validated'),
            'candidates as candidates_year1'     => fn($q) => $q->where('year', '1')->where('status', 'validated'),
            'candidates as candidates_year2'     => fn($q) => $q->where('year', '2')->where('status', 'validated'),
        ])->get();

        // Dernières actions
        $recentActions = ActionLog::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'stats'          => $stats,
            'by_department'  => $byDepartment,
            'recent_actions' => $recentActions,
        ]);
    }

    // Journal des actions (traçabilité complète)
    public function logs(Request $request)
    {
        $query = ActionLog::with('user')
            ->orderBy('created_at', 'desc');

        // Filtres
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $logs = $query->paginate(30);

        return response()->json($logs);
    }
}