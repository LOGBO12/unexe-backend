<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    // ==================== RELATIONS ====================

    public function candidates()
    {
        return $this->hasMany(Candidate::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function invitations()
    {
        return $this->hasMany(Invitation::class);
    }

    // ==================== HELPERS ====================

    // Candidats validés de 1ère année
    public function candidatesYear1()
    {
        return $this->hasMany(Candidate::class)
            ->where('year', '1')
            ->where('status', 'validated');
    }

    // Candidats validés de 2ème année
    public function candidatesYear2()
    {
        return $this->hasMany(Candidate::class)
            ->where('year', '2')
            ->where('status', 'validated');
    }
}