<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CandidateScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidate_id',
        'phase_id',
        'score',
        'status',
        'comment',
        'graded_by',
        'graded_at',
    ];

    protected $casts = [
        'score'      => 'float',
        'graded_at'  => 'datetime',
    ];

    // ==================== RELATIONS ====================

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    public function phase()
    {
        return $this->belongsTo(CompetitionPhase::class, 'phase_id');
    }

    public function gradedBy()
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}