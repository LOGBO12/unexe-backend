<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CommitteeMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'position',
        'bio',
        'photo',
        'display_order',
    ];

    // ==================== RELATIONS ====================

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}