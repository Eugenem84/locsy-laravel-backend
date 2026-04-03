<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PhotographerProfile;
use Illuminate\Http\Request;

class PhotographerController extends Controller
{
    public function show($userId)
    {
        $photographer = PhotographerProfile::with(['city', 'user.photos.location'])->where('user_id', $userId)->firstOrFail();

        $photos = $photographer->user->photos->map(function ($photo) {
            return [
                'id' => $photo->id,
                'full_url' => $photo->full_url,
                'user_id' => $photo->user_id,
                'location' => $photo->location ? [
                    'id' => $photo->location->id,
                    'name' => $photo->location->name,
                ] : null,
            ];
        });

        $profileData = $photographer->toArray();
        // Unset the user relation to avoid exposing unnecessary user data
        unset($profileData['user']);

        // Add avatar from user model
        $profileData['avatar'] = $photographer->user->avatar;
        $profileData['photos'] = $photos;

        return response()->json($profileData);
    }
}
