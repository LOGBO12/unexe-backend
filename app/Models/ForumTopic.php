<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ForumTopic extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'type',
        'author_id',
        'is_pinned',
        'is_closed',
        'replies_count',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_closed' => 'boolean',
    ];

    // ==================== RELATIONS ====================

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function replies()
    {
        return $this->hasMany(ForumReply::class, 'topic_id');
    }

    // Uniquement les réponses de premier niveau (pas les sous-réponses)
    public function rootReplies()
    {
        return $this->hasMany(ForumReply::class, 'topic_id')
            ->whereNull('parent_id')
            ->orderBy('created_at', 'asc');
    }

    // ==================== SCOPES ====================

    public function scopeAnnouncements($query)
    {
        return $query->where('type', 'announcement');
    }

    public function scopeDiscussions($query)
    {
        return $query->where('type', 'discussion');
    }

    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    // ==================== HELPERS ====================

    public function isAnnouncement(): bool
    {
        return $this->type === 'announcement';
    }

    public function isOpen(): bool
    {
        return !$this->is_closed;
    }
}