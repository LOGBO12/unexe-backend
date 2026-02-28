<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, HasFactory;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'avatar',
        'is_profile_complete',
        'invited_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_profile_complete' => 'boolean',
        'password' => 'hashed',
    ];

    // ==================== RELATIONS ====================

    public function committeeMember()
    {
        return $this->hasOne(CommitteeMember::class);
    }

    public function candidate()
    {
        return $this->hasOne(Candidate::class);
    }

    public function application()
    {
        return $this->hasOne(Application::class);
    }

    public function invitedBy()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function invitedUsers()
    {
        return $this->hasMany(User::class, 'invited_by');
    }

    public function actionLogs()
    {
        return $this->hasMany(ActionLog::class);
    }

    public function forumTopics()
    {
        return $this->hasMany(ForumTopic::class, 'author_id');
    }

    public function forumReplies()
    {
        return $this->hasMany(ForumReply::class);
    }

    public function validatedCandidates()
    {
        return $this->hasMany(Candidate::class, 'validated_by');
    }

    public function reviewedApplications()
    {
        return $this->hasMany(Application::class, 'reviewed_by');
    }

    public function sentInvitations()
    {
        return $this->hasMany(Invitation::class, 'invited_by');
    }

    // ==================== HELPERS ====================

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isComite(): bool
    {
        return in_array($this->role, ['super_admin', 'comite']);
    }

    public function isCandidat(): bool
    {
        return $this->role === 'candidat';
    }

    public function isValidatedCandidat(): bool
    {
        return $this->role === 'candidat'
            && $this->candidate
            && $this->candidate->status === 'validated';
    }

    public function canAccessForum(): bool
    {
        return $this->isComite() || $this->isValidatedCandidat();
    }

    public function canAccessCommunity(): bool
    {
        return $this->isComite() || $this->isValidatedCandidat();
    }
}