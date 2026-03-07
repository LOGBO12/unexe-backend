<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory; // ← AJOUTER cet import

class CommitteePage extends Model
{
    use HasFactory;

    protected $table = 'committee_page';

    protected $fillable = [
        'project_description',
        'vision',
        'objectives',
        'team_photo',
    ];

    protected $casts = [
        'objectives' => 'array',
    ];

    public function getTeamPhotoUrlAttribute(): ?string
    {
        return $this->team_photo
            ? asset('storage/' . $this->team_photo)
            : null;
    }
}