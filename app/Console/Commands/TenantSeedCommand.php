<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Database\Seeders\TenantDemoSeeder;
use Illuminate\Console\Command;

class TenantSeedCommand extends Command
{
    protected $signature = 'tenant:seed {slug}';
    protected $description = 'Run demo seeder on a specific tenant';

    public function handle(): int
    {
        $slug = $this->argument('slug');
        $tenant = Tenant::where('slug', $slug)->first();

        if (! $tenant) {
            $this->error("Tenant with slug '{$slug}' not found.");
            return self::FAILURE;
        }

        $this->info("Seeding tenant: {$tenant->name} ({$tenant->slug})...");

        $tenant->run(function () {
            $seeder = new TenantDemoSeeder();
            $seeder->run();
        });

        $this->info('Tenant seeded successfully!');

        return self::SUCCESS;
    }
}
