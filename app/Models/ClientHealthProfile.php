<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ClientHealthProfile extends Model
{
    use HasUuids;

    protected $fillable = [
        'client_id',
        'allergies',
        'allergies_notes',
        'medical_conditions',
        'medical_notes',
        'current_medications',
        'contraindications',
        'avoid_zones',
        'pressure_preference',
        'personal_preferences',
        'therapist_notes',
        'last_updated_by_client',
        'last_updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'allergies' => 'array',
            'medical_conditions' => 'array',
            'avoid_zones' => 'array',
            'personal_preferences' => 'array',
            'pressure_preference' => 'integer',
            'last_updated_by_client' => 'datetime',
        ];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by_user_id');
    }

    public function isOutdated(): bool
    {
        if (!$this->last_updated_by_client) {
            return true;
        }

        return $this->last_updated_by_client->lt(Carbon::now()->subMonths(6));
    }

    public function hasCriticalAlerts(): bool
    {
        return !empty($this->allergies)
            || !empty($this->medical_conditions)
            || !empty($this->avoid_zones)
            || !empty($this->contraindications);
    }

    public function getAlertSummary(): array
    {
        return [
            'allergies' => $this->allergies ?? [],
            'medical_conditions' => $this->medical_conditions ?? [],
            'avoid_zones' => $this->avoid_zones ?? [],
            'contraindications' => $this->contraindications,
            'pressure_label' => $this->getPressureLabel(),
            'personal_preferences' => $this->personal_preferences ?? [],
            'therapist_notes' => $this->therapist_notes,
            'is_outdated' => $this->isOutdated(),
        ];
    }

    public function getPressureLabel(): string
    {
        return match ($this->pressure_preference) {
            1 => 'Muy suave',
            2 => 'Suave',
            3 => 'Media',
            4 => 'Fuerte',
            5 => 'Muy fuerte',
            default => 'Suave',
        };
    }
}
