<?php

namespace App\Services;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Support\Facades\Storage;

class ServiceService
{
    public function getGroupedByCategory(?string $search = null): array
    {
        $categories = ServiceCategory::with(['services' => function ($q) use ($search) {
            if ($search) {
                $q->where('name', 'like', "%{$search}%");
            }
            $q->orderBy('sort_order');
        }])
            ->orderBy('sort_order')
            ->get();

        return $categories->toArray();
    }

    public function store(array $data, $image = null): Service
    {
        if ($image) {
            $data['image_path'] = $image->store('services', 'public');
        }

        return Service::create($data);
    }

    public function update(Service $service, array $data, $image = null): Service
    {
        if ($image) {
            if ($service->image_path) {
                Storage::disk('public')->delete($service->image_path);
            }
            $data['image_path'] = $image->store('services', 'public');
        }

        $service->update($data);
        return $service;
    }

    public function delete(Service $service): void
    {
        if ($service->image_path) {
            Storage::disk('public')->delete($service->image_path);
        }
        $service->delete();
    }

    public function toggleActive(Service $service): Service
    {
        $service->update(['is_visible' => !$service->is_visible]);
        return $service;
    }

    public function reorder(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            Service::where('id', $id)->update(['sort_order' => $index]);
        }
    }
}
