<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class LocationController extends Controller
{
    public function index(Request $request)
    {
        // Добавляем жадную загрузку фотографий
        $query = Location::with('city', 'photos');

        if ($request->has('city_id')) {
            $query->where('city_id', $request->input('city_id'));
        }

        return $query->get();
    }

    public function show($id)
    {
        // Находим локацию с ее фотографиями
        // findOrFail автоматически вернет 404, если локация не найдена
        $location = Location::with('photos.user')->findOrFail($id);

        return $location;
    }

    public function getLocationsByBounds(Request $request) {
        // Валидация, что все 4 параметра пришли
        $request->validate([
            'sw_lat' => 'required|numeric',
            'sw_lng' => 'required|numeric',
            'ne_lat' => 'required|numeric',
            'ne_lng' => 'required|numeric',
        ]);

        $locations = Location::with('photos')->whereBetween('latitude', [$request->sw_lat, $request->ne_lat])
            ->whereBetween('longitude', [$request->sw_lng, $request->ne_lng])
            ->limit(100) // Обязательно добавьте лимит!
            ->get();

        return response()->json($locations);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'city_id' => 'required|exists:cities,id',
            'photos' => 'nullable|array',
            // Allow common image formats, but remove size limit and SVG
            'photos.*' => 'image|mimes:jpeg,png,jpg,gif',
        ]);

        $location = Location::create([
            'name' => $request->name,
            'description' => $request->description,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'city_id' => $request->city_id,
            'user_id' => Auth::id(),
        ]);

        if ($request->hasFile('photos')) {
            // Create an image manager instance with GD driver
            $manager = new ImageManager(new Driver());

            foreach ($request->file('photos') as $photoFile) {
                // Read image from uploaded file
                $image = $manager->read($photoFile);

                // Resize image if its width is greater than 3840px, maintaining aspect ratio
                if ($image->width() > 3840) {
                    $image->scale(width: 3840);
                }

                if($image->height() > 2048) {
                    $image->scale(height: 2048);
                }

                // Generate a unique name, forcing jpg extension
                $filename = Str::random(40) . '.jpg';
                $path = 'locations/' . $filename;

                // Encode the image to JPEG format (quality 80) and save to public storage
                $encodedImage = $image->toJpeg(80);
                Storage::disk('public')->put($path, (string) $encodedImage);

                Photo::create([
                    'location_id' => $location->id,
                    'path' => $path,
                ]);
            }
        }

        return response()->json($location->load('photos'), 201);
    }
}
