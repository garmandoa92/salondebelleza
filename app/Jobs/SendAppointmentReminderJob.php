<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Models\Tenant;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAppointmentReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Tenant::all()->each(function (Tenant $tenant) {
            $tenant->run(function () use ($tenant) {
                $whatsApp = new WhatsAppService();

                // 24h reminders
                $appointments24h = Appointment::with(['client', 'service', 'stylist'])
                    ->whereNull('reminder_sent_at')
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->whereBetween('starts_at', [now()->addHours(23), now()->addHours(25)])
                    ->get();

                foreach ($appointments24h as $apt) {
                    if (! $apt->client?->phone) continue;
                    $whatsApp->sendTemplate($apt->client->phone, 'appointment_reminder_24h', [
                        ['type' => 'body', 'parameters' => [
                            ['type' => 'text', 'text' => $apt->client->first_name],
                            ['type' => 'text', 'text' => $tenant->name],
                            ['type' => 'text', 'text' => $apt->starts_at->format('d/m/Y')],
                            ['type' => 'text', 'text' => $apt->starts_at->format('H:i')],
                            ['type' => 'text', 'text' => $apt->service?->name ?? ''],
                            ['type' => 'text', 'text' => $apt->stylist?->name ?? ''],
                        ]],
                    ]);
                    $apt->update(['reminder_sent_at' => now()]);
                }

                // 2h reminders
                $appointments2h = Appointment::with(['client'])
                    ->whereNotNull('reminder_sent_at')
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->whereBetween('starts_at', [now()->addMinutes(110), now()->addMinutes(130)])
                    ->get();

                foreach ($appointments2h as $apt) {
                    if (! $apt->client?->phone) continue;
                    $whatsApp->sendTemplate($apt->client->phone, 'appointment_reminder_2h', [
                        ['type' => 'body', 'parameters' => [
                            ['type' => 'text', 'text' => $apt->client->first_name],
                            ['type' => 'text', 'text' => $apt->starts_at->format('H:i')],
                            ['type' => 'text', 'text' => $tenant->name],
                        ]],
                    ]);
                }
            });
        });
    }
}
