<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification
{
    use Queueable;

    public function __construct(
        public array $products,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $names = collect($this->products)->pluck('name')->take(3)->implode(', ');

        return [
            'type' => 'low_stock',
            'title' => 'Stock bajo',
            'message' => "Productos con stock bajo: {$names}",
            'products' => $this->products,
            'icon' => 'alert',
        ];
    }
}
