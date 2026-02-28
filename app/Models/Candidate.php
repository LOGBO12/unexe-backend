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
    ];

    protected $casts = [
        'validated_at' => 'datetime',
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