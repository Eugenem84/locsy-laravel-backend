<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\PhotographerProfile;

class PhotographerController extends Controller
{
    /**
     * Публичный профиль фотографа: портфолио и координаты мест, где он снимал —
     * чтобы клиент мог открыть портфолио и увидеть точки съёмок на карте.
     */
    public function show($userId)
    {
        $photographer = PhotographerProfile::with('city')
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->firstOrFail();

        $user = $photographer->user;

        $photos = Photo::query()
            ->approved()
            ->where('user_id', $photographer->user_id)
            ->with('location:id,name,latitude,longitude,city_id')
            ->latest()
            ->get()
            ->map(fn (Photo $photo) => [
                'id' => $photo->id,
                'full_url' => $photo->full_url,
                'user_id' => $photo->user_id,
                'location' => $photo->location ? [
                    'id' => $photo->location->id,
                    'name' => $photo->location->name,
                    'latitude' => (float) $photo->location->latitude,
                    'longitude' => (float) $photo->location->longitude,
                ] : null,
            ]);

        $profileData = $photographer->toArray();
        $profileData['avatar'] = $user->avatar;
        $profileData['photos'] = $photos;
        $profileData['photos_count'] = $photos->count();
        $profileData['shooting_spots'] = $photos
            ->pluck('location')
            ->filter()
            ->unique('id')
            ->values();

        return response()->json($profileData);
    }
}
