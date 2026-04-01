<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class TenantListCommand extends Command
{
    protected $signature = 'tenant:list';
    protected $description = 'List all tenants';

    public function handle(): int
    {
        $tenants = Tenant::with('plan')->get();

        if ($tenants->isEmpty()) {
            $this->info('No tenants found.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Slug', 'Plan', 'Trial Ends', 'Active'],
            $tenants->map(fn ($t) => [
                substr($t->id, 0, 8) . '...',
                $t->name,
                $t->slug,
                $t->plan?->name ?? 'N/A',
                $t->trial_ends_at?->format('Y-m-d') ?? 'N/A',
                $t->is_active ? 'Yes' : 'No',
            ])
        );

        return self::SUCCESS;
    }
}
