<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActionLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'target_type',
        'target_id',
        'details',
        'ip_address',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    // ==================== RELATIONS ====================

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ==================== HELPER STATIQUE ====================

    /**
     * Créer une entrée de log facilement depuis n'importe où
     *
     * Usage :
     * ActionLog::log($user, 'validate_candidate', $candidate, ['note' => 'Bon dossier']);
     */
    public static function log(User $user, string $action, $target = null, array $details = [], string $ip = null): self
    {
        return self::create([
            'user_id'     => $user->id,
            'action'      => $action,
            'target_type' => $target ? get_class($target) : null,
            'target_id'   => $target ? $target->id : null,
            'details'     => $details,
            'ip_address'  => $ip ?? request()->ip(),
        ]);
    }
}