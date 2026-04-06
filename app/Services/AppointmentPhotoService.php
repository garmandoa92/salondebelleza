<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
class AppointmentPhotoService
{
    public function store(
        UploadedFile $file,
        Appointment $appointment,
        string $type,
        ?string $caption,
        User $takenBy
    ): AppointmentPhoto {
        $tenantSlug = tenant('id') ?? 'default';
        $dir = "photos/{$tenantSlug}/{$appointment->client_id}";
        $filename = $type . '_' . uniqid() . '.jpg';

        $path = $file->storeAs($dir, $filename, 'public_central');

        // Generate thumbnail
        $thumbPath = null;
        try {
            $thumbFilename = 'thumb_' . $filename;
            $thumbFullPath = storage_path("app/public/{$dir}/{$thumbFilename}");
            $sourceFullPath = storage_path("app/public/{$path}");

            if (function_exists('imagecreatefromjpeg') || extension_loaded('gd')) {
                $this->createThumbnail($sourceFullPath, $thumbFullPath, 300);
                $thumbPath = "{$dir}/{$thumbFilename}";
            }
        } catch (\Throwable $e) {
            // Thumbnail generation failed, continue without it
        }

        return AppointmentPhoto::create([
            'appointment_id' => $appointment->id,
            'client_id' => $appointment->client_id,
            'type' => $type,
            'photo_path' => $path,
            'thumbnail_path' => $thumbPath,
            'caption' => $caption,
            'taken_by' => $takenBy->id,
        ]);
    }

    public function delete(AppointmentPhoto $photo): void
    {
        Storage::disk('public_central')->delete($photo->photo_path);
        if ($photo->thumbnail_path) {
            Storage::disk('public_central')->delete($photo->thumbnail_path);
        }
        $photo->delete();
    }

    public function getClientPhotos(string $clientId): Collection
    {
        return AppointmentPhoto::where('client_id', $clientId)
            ->with(['appointment.service:id,name', 'appointment.stylist:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('appointment_id');
    }

    public function update(AppointmentPhoto $photo, array $data): AppointmentPhoto
    {
        $photo->update(array_intersect_key($data, array_flip(['caption', 'is_visible_to_client'])));
        return $photo;
    }

    private function createThumbnail(string $source, string $dest, int $maxWidth): void
    {
        $info = getimagesize($source);
        if (!$info) return;

        [$w, $h, $imgType] = $info;
        $src = match ($imgType) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($source),
            IMAGETYPE_PNG => imagecreatefrompng($source),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($source) : null,
            default => null,
        };
        if (!$src) return;

        $ratio = $maxWidth / $w;
        $newW = $maxWidth;
        $newH = (int) ($h * $ratio);

        if ($w <= $maxWidth) {
            copy($source, $dest);
            imagedestroy($src);
            return;
        }

        $thumb = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

        $dir = dirname($dest);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        imagejpeg($thumb, $dest, 85);
        imagedestroy($src);
        imagedestroy($thumb);
    }
}
