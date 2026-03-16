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
}
