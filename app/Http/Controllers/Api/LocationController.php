<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;

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
        $location = Location::with('photos')->findOrFail($id);

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

}
