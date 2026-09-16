<?php

namespace App\Http\Controllers\Api;

use App\Enums\PhotoStatus;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Photo;
use App\Services\PhotoStorage;
use App\Settings\ModerationSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PhotoController extends Controller
{
    public function store(Request $request, Location $location)
    {
        $request->validate([
            'photos' => 'required|array|max:10',
            'photos.*' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:20480',
        ]);

        $settings = app(ModerationSettings::class);
        $needsModeration = $settings->photo_moderation_enabled;
        $status = $needsModeration ? PhotoStatus::Pending : PhotoStatus::Approved;

        $photoStorage = app(PhotoStorage::class);

        $photos = collect($request->file('photos'))->map(
            fn ($file) => $photoStorage->store($file, $location->id, $request->user()->id, $status)
        );

        return response()->json([
            'photos' => $photos->map(fn (Photo $photo) => [
                'id' => $photo->id,
                'full_url' => $photo->full_url,
                'status' => $photo->status,
            ]),
            'needs_moderation' => $needsModeration,
        ], 201);
    }

    public function destroy(Photo $photo)
    {
        if (auth()->id() !== $photo->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        Storage::disk('public')->delete($photo->path);
        $photo->delete();

        return response()->noContent();
    }
}
