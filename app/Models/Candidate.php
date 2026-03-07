<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Candidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'department_id',
        'filiere',
        'year',
        'bio',
        'photo',
        'phone',
        'matricule',
        'status',
        'validated_by',
        'validated_at',
        'rejection_reason',
        'is_visible',
        'current_phase',
        'is_leader',
    ];

    protected $casts = [
        'validated_at' => 'datetime',
        'is_visible'   => 'boolean',
        'is_leader'    => 'boolean',
    ];

    // ==================== RELATIONS ====================

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function validatedBy()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /**
     * Scores du candidat dans les phases du concours
     */
    public function scores()
    {
        return $this->hasMany(CandidateScore::class, 'candidate_id');
    }

    // ==================== SCOPES ====================

    public function scopeValidated($query)
    {
        return $query->where('status', 'validated');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeYear1($query)
    {
        return $query->where('year', '1');
    }

    public function scopeYear2($query)
    {
        return $query->where('year', '2');
    }

    // ==================== HELPERS ====================

    public function isValidated(): bool
    {
        return $this->status === 'validated';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}