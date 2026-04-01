<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewOnlineBookingNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Appointment $appointment,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'new_online_booking',
            'title' => 'Nueva reserva online',
            'message' => "{$this->appointment->client?->full_name} reservo {$this->appointment->service?->name} para el {$this->appointment->starts_at->format('d/m H:i')}",
            'appointment_id' => $this->appointment->id,
            'icon' => 'calendar',
        ];
    }
}
