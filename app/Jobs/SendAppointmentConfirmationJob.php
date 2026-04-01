<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAppointmentConfirmationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $appointmentId,
    ) {}

    public function handle(WhatsAppService $whatsApp): void
    {
        $appointment = Appointment::with(['client', 'service', 'stylist'])->find($this->appointmentId);
        if (! $appointment || ! $appointment->client?->phone) return;

        $whatsApp->sendTemplate($appointment->client->phone, 'appointment_confirmation', [
            ['type' => 'body', 'parameters' => [
                ['type' => 'text', 'text' => $appointment->client->first_name],
                ['type' => 'text', 'text' => tenant()?->name ?? 'el salon'],
                ['type' => 'text', 'text' => $appointment->starts_at->format('d/m/Y')],
                ['type' => 'text', 'text' => $appointment->starts_at->format('H:i')],
                ['type' => 'text', 'text' => $appointment->service?->name ?? ''],
                ['type' => 'text', 'text' => $appointment->stylist?->name ?? ''],
            ]],
        ]);
    }
}
