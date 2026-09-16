<?php

namespace App\Services;

use App\Enums\PhotoStatus;
use App\Models\Photo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Единая точка обработки и сохранения фотографий локальных локаций:
 * нормализует размер/формат и создаёт запись в БД с нужным статусом модерации.
 */
class PhotoStorage
{
    private const MAX_WIDTH = 3840;

    private const MAX_HEIGHT = 2048;

    private const JPEG_QUALITY = 80;

    public function store(
        UploadedFile $file,
        int $locationId,
        ?int $userId,
        PhotoStatus $status
    ): Photo {
        $manager = new ImageManager(new Driver);
        $image = $manager->read($file);

        if ($image->width() > self::MAX_WIDTH) {
            $image->scale(width: self::MAX_WIDTH);
        }

        if ($image->height() > self::MAX_HEIGHT) {
            $image->scale(height: self::MAX_HEIGHT);
        }

        $path = 'locations/'.Str::random(40).'.jpg';

        Storage::disk('public')->put(
            $path,
            (string) $image->toJpeg(self::JPEG_QUALITY)
        );

        return Photo::create([
            'location_id' => $locationId,
            'user_id' => $userId,
            'path' => $path,
            'status' => $status,
        ]);
    }
}
