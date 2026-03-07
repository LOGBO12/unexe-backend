<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CompetitionPhase extends Model
{
    use HasFactory;

    protected $fillable = [
        'phase_number',
        'name',
        'description',
        'total_phases',
        'status',
        'is_final',
        'created_by',
    ];

    protected $casts = [
        'is_final' => 'boolean',
    ];

    // ==================== RELATIONS ====================

    public function scores()
    {
        return $this->hasMany(CandidateScore::class, 'phase_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ==================== HELPERS ====================

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}