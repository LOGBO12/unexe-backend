<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class RegistrationSetting extends Model
{
    /**
     * IMPORTANT : toutes les colonnes doivent être dans $fillable
     * sinon Eloquent les ignore silencieusement lors du save().
     */
    protected $fillable = [
        'registration_open',
        'registration_deadline',
        'closed_message',
    ];

    protected $casts = [
        'registration_open'     => 'boolean',
        // NE PAS caster en 'datetime' ici — on gère manuellement avec Carbon
        // pour éviter les conflits de timezone
    ];

    /**
     * Mutateur : forcer Carbon sur registration_deadline
     */
    public function getRegistrationDeadlineAttribute($value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }

    /**
     * Accesseur : s'assurer que la valeur est bien formatée pour MySQL
     */
    public function setRegistrationDeadlineAttribute($value): void
    {
        if ($value === null || $value === '' || $value === 'null') {
            $this->attributes['registration_deadline'] = null;
        } elseif ($value instanceof Carbon) {
            $this->attributes['registration_deadline'] = $value->format('Y-m-d H:i:s');
        } else {
            try {
                $this->attributes['registration_deadline'] = Carbon::parse($value)->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                $this->attributes['registration_deadline'] = null;
            }
        }
    }

    /**
     * Récupère le singleton (toujours id = 1).
     */
    public static function current(): self
    {
        return self::firstOrCreate(
            ['id' => 1],
            [
                'registration_open'     => true,
                'registration_deadline' => null,
                'closed_message'        => 'Les inscriptions sont actuellement fermées.',
            ]
        );
    }

    /**
     * Les inscriptions sont-elles ouvertes ?
     */
    public function isOpen(): bool
    {
        if (! $this->registration_open) {
            return false;
        }

        $deadline = $this->registration_deadline;

        if ($deadline && Carbon::now()->isAfter($deadline)) {
            return false;
        }

        return true;
    }
}