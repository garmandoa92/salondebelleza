<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, SoftDeletes;

    public function getIncrementing(): bool
    {
        return false;
    }

    public function getKeyType(): string
    {
        return 'string';
    }

    protected $fillable = [
        'id',
        'name',
        'slug',
        'ruc',
        'razon_social',
        'phone',
        'address',
        'logo_path',
        'plan_id',
        'trial_ends_at',
        'settings',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'data' => 'array',
            'trial_ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'ruc',
            'razon_social',
            'phone',
            'address',
            'logo_path',
            'plan_id',
            'trial_ends_at',
            'settings',
            'is_active',
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function tenantUsers()
    {
        return $this->hasMany(TenantUser::class);
    }

    public function owner()
    {
        return $this->hasOne(TenantUser::class)->where('role', 'owner');
    }

    public function hasActiveTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function hasActiveSubscription(): bool
    {
        // Will be implemented with Stripe in Session 10
        return false;
    }

    public function isUsable(): bool
    {
        return $this->is_active && ($this->hasActiveTrial() || $this->hasActiveSubscription());
    }
}
