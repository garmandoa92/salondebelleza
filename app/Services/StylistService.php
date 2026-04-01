<?php

namespace App\Services;

use App\Models\Stylist;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class StylistService
{
    public function store(array $data, $photo = null): Stylist
    {
        if ($photo) {
            $data['photo_path'] = $photo->store('stylists', 'public');
        }

        $stylist = Stylist::create($data);

        // Create user account if email provided
        if (!empty($data['email'])) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make('password'),
                'is_active' => true,
            ]);
            $user->assignRole('stylist');
            $stylist->update(['user_id' => $user->id]);
        }

        return $stylist;
    }

    public function update(Stylist $stylist, array $data, $photo = null): Stylist
    {
        if ($photo) {
            if ($stylist->photo_path) {
                Storage::disk('public')->delete($stylist->photo_path);
            }
            $data['photo_path'] = $photo->store('stylists', 'public');
        }

        $branchIds = $data['branch_ids'] ?? null;
        unset($data['branch_ids']);

        $stylist->update($data);

        if ($branchIds !== null) {
            $stylist->branches()->sync($branchIds);
        }

        return $stylist;
    }

    public function delete(Stylist $stylist): void
    {
        if ($stylist->photo_path) {
            Storage::disk('public')->delete($stylist->photo_path);
        }
        $stylist->delete();
    }

    public function toggleActive(Stylist $stylist): Stylist
    {
        $stylist->update(['is_active' => !$stylist->is_active]);
        return $stylist;
    }
}
