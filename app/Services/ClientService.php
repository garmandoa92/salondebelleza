<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Http\Request;

class ClientService
{
    public function getFiltered(Request $request)
    {
        $query = Client::with('preferredStylist:id,name')
            ->withCount('appointments');

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('cedula', 'like', "%{$search}%");
            });
        }

        if ($tag = $request->tag) {
            $query->whereJsonContains('tags', $tag);
        }

        if ($stylistId = $request->stylist_id) {
            $query->where('preferred_stylist_id', $stylistId);
        }

        if ($request->inactive) {
            $query->where(function ($q) {
                $q->where('last_visit_at', '<', now()->subDays(60))
                    ->orWhereNull('last_visit_at');
            });
        }

        $sortField = $request->sort ?? 'created_at';
        $sortDir = $request->direction ?? 'desc';
        $allowed = ['first_name', 'total_spent', 'last_visit_at', 'visit_count', 'created_at'];
        if (in_array($sortField, $allowed)) {
            $query->orderBy($sortField, $sortDir);
        }

        return $query->paginate(25);
    }

    public function store(array $data): Client
    {
        return Client::create($data);
    }

    public function update(Client $client, array $data): Client
    {
        $client->update($data);
        return $client;
    }

    public function delete(Client $client): void
    {
        $client->delete();
    }
}
