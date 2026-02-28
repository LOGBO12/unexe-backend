<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommitteePage extends Model
{
    protected $table = 'committee_page';

    protected $fillable = [
        'project_description',
        'vision',
        'objectives',
        'team_photo',
        'updated_by',
    ];

    protected $casts = [
        'objectives' => 'array', // stocké en JSON, retourné en tableau
    ];

    // ==================== RELATIONS ====================

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}