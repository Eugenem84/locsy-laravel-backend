<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
//use Intervention\Image\Laravel\Facades\Image;
use Illuminate\Support\Str;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class PhotoController extends Controller
{
    public function store(Request $request, Location $location)
    {
        $request->validate([
            'photos.*' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:100000',
        ]);

        $photos = [];
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

        return response()->json($photos, 201);
    }
}
