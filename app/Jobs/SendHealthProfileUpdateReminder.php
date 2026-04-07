<?php

namespace App\Jobs;

use App\Models\Client;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

class SendHealthProfileUpdateReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(WhatsAppService $whatsapp): void
    {
        if (!$whatsapp->isConfigured()) {
            return;
        }

        tenancy()->runForMultiple(
            null,
            function (TenantWithDatabase $tenant) use ($whatsapp) {
                try {
                    $clients = Client::whereHas('healthProfile', function ($q) {
                        $q->where('last_updated_by_client', '<', Carbon::now()->subMonths(6))
                            ->orWhereNull('last_updated_by_client');
                    })
                        ->whereHas('appointments', function ($q) {
                            $q->whereBetween('starts_at', [
                                Carbon::now(),
                                Carbon::now()->addDays(7),
                            ]);
                        })
                        ->with('healthProfile')
                        ->get();

                    foreach ($clients as $client) {
                        if (!$client->phone) {
                            continue;
                        }

                        $name = $client->first_name;
                        $whatsapp->sendText(
                            $client->phone,
                            "Hola {$name}, te recordamos actualizar tu ficha de salud antes de tu proxima visita. Toma solo 2 minutos. Gracias!"
                        );
                    }

                    Log::info("Tenant {$tenant->id}: {$clients->count()} recordatorios de ficha de salud");
                } catch (\Throwable $e) {
                    Log::error("Error enviando recordatorios ficha salud para {$tenant->id}: {$e->getMessage()}");
                }
            }
        );
    }
}
