<?php

namespace App\Console\Commands;

use App\Services\TenantService;
use Illuminate\Console\Command;

class TenantCreateCommand extends Command
{
    protected $signature = 'tenant:create {name} {slug} {email} {plan=profesional}';
    protected $description = 'Create a new tenant (salon) from CLI';

    public function handle(TenantService $tenantService): int
    {
        $this->info('Creating tenant...');

        $result = $tenantService->createTenant([
            'salon_name' => $this->argument('name'),
            'slug' => $this->argument('slug'),
            'name' => 'Admin',
            'email' => $this->argument('email'),
            'password' => 'password',
            'phone' => '0000000000',
            'plan' => $this->argument('plan'),
        ]);

        $tenant = $result['tenant'];

        $this->info("Tenant created: {$tenant->name}");
        $this->info("Slug: {$tenant->slug}");
        $this->info("Database: tenant_{$tenant->id}");
        $this->info("URL: http://{$tenant->slug}." . config('tenancy.central_domains')[0]);
        $this->info("Trial ends: {$tenant->trial_ends_at->format('Y-m-d')}");
        $this->warn("Default password: 'password' — change it immediately!");

        return self::SUCCESS;
    }
}
