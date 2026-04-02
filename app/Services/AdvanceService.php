<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientAdvance;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdvanceService
{
    public function register(array $data, User $user): ClientAdvance
    {
        return DB::transaction(function () use ($data, $user) {
            $advance = ClientAdvance::create([
                'client_id' => $data['client_id'],
                'appointment_id' => $data['appointment_id'] ?? null,
                'type' => $data['type'] ?? 'advance',
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'received_by' => $user->id,
                'status' => 'pending',
            ]);

            Client::where('id', $data['client_id'])
                ->increment('balance', $data['amount']);

            return $advance;
        });
    }

    public function apply(ClientAdvance $advance, Sale $sale, float $amount): void
    {
        DB::transaction(function () use ($advance, $sale, $amount) {
            $applyAmount = min($amount, (float) $advance->amount);

            $advance->update([
                'status' => 'applied',
                'sale_id' => $sale->id,
            ]);

            Client::where('id', $advance->client_id)
                ->decrement('balance', $applyAmount);
        });
    }

    public function applyBalanceToSale(Client $client, Sale $sale, float $amount): void
    {
        DB::transaction(function () use ($client, $sale, $amount) {
            $remaining = $amount;

            $pendingAdvances = ClientAdvance::where('client_id', $client->id)
                ->where('status', 'pending')
                ->orderBy('created_at')
                ->get();

            foreach ($pendingAdvances as $advance) {
                if ($remaining <= 0) break;

                $applyAmount = min($remaining, (float) $advance->amount);
                $advance->update([
                    'status' => 'applied',
                    'sale_id' => $sale->id,
                ]);
                $remaining -= $applyAmount;
            }

            Client::where('id', $client->id)
                ->decrement('balance', $amount);
        });
    }

    public function refund(ClientAdvance $advance, ?string $notes = null): void
    {
        DB::transaction(function () use ($advance, $notes) {
            $advance->update([
                'status' => 'refunded',
                'notes' => $notes ?? $advance->notes,
            ]);

            Client::where('id', $advance->client_id)
                ->decrement('balance', $advance->amount);
        });
    }
}
