<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class Invitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'role',
        'token',
        'default_password',
        'invited_by',
        'department_id',
        'used_at',
        'expires_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // ==================== RELATIONS ====================

    public function invitedBy()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    // ==================== HELPERS ====================

    public function isExpired(): bool
    {
        return Carbon::now()->isAfter($this->expires_at);
    }

    public function isUsed(): bool
    {
        return !is_null($this->used_at);
    }

    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isUsed();
    }
}