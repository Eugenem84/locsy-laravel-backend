<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PhotographerProfile;
use Illuminate\Http\Request;

class PhotographerController extends Controller
{
    public function show($userId)
    {
        $photographer = PhotographerProfile::with(['city', 'user.photos'])->where('user_id', $userId)->firstOrFail();

        $photos = $photographer->user->photos->map(function ($photo) {
            return [
                'id' => $photo->id,
                'full_url' => $photo->full_url,
                'user_id' => $photo->user_id,
            ];
        });

        $profileData = $photographer->toArray();
        // Unset the user relation to avoid exposing unnecessary user data
        unset($profileData['user']);
        $profileData['photos'] = $photos;


        return response()->json($profileData);
    }
}
