<?php

namespace App\Jobs;

use App\Services\ExpenseService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

class GenerateRecurringExpensesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ExpenseService $service): void
    {
        $now = Carbon::now();

        tenancy()->runForMultiple(
            null,
            function (TenantWithDatabase $tenant) use ($service, $now) {
                try {
                    $count = $service->generateRecurringExpenses($now->year, $now->month);
                    Log::info("Tenant {$tenant->id}: {$count} gastos recurrentes generados");
                } catch (\Throwable $e) {
                    Log::error("Error generando recurrentes para {$tenant->id}: {$e->getMessage()}");
                }
            }
        );
    }
}
