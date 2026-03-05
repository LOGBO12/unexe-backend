<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommitteePage extends Model
{
    protected $fillable = [
        'project_description',
        'vision',
        'objectives',
        'team_photo',
    ];

    protected $casts = [
        // objectives est stocké en JSON dans la DB
        'objectives' => 'array',
    ];

    /**
     * URL complète de la photo (accesseur)
     */
    public function getTeamPhotoUrlAttribute(): ?string
    {
        return $this->team_photo
            ? asset('storage/' . $this->team_photo)
            : null;
    }
}