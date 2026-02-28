<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ForumReply extends Model
{
    use HasFactory;

    protected $fillable = [
        'topic_id',
        'user_id',
        'content',
        'is_official_response',
        'parent_id',
    ];

    protected $casts = [
        'is_official_response' => 'boolean',
    ];

    // ==================== RELATIONS ====================

    public function topic()
    {
        return $this->belongsTo(ForumTopic::class, 'topic_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Réponse parente (si c'est une sous-réponse)
    public function parent()
    {
        return $this->belongsTo(ForumReply::class, 'parent_id');
    }

    // Sous-réponses
    public function children()
    {
        return $this->hasMany(ForumReply::class, 'parent_id')
            ->orderBy('created_at', 'asc');
    }

    // ==================== BOOT — Mise à jour automatique du compteur ====================

    protected static function boot()
    {
        parent::boot();

        // Incrémenter replies_count quand une réponse est créée
        static::created(function ($reply) {
            $reply->topic()->increment('replies_count');
        });

        // Décrémenter replies_count quand une réponse est supprimée
        static::deleted(function ($reply) {
            $reply->topic()->decrement('replies_count');
        });
    }
}