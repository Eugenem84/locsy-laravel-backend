<?php

namespace App\Http\Controllers\Api;

use App\Enums\LocationStatus;
use App\Enums\PhotoStatus;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\PhotoStorage;
use App\Settings\ModerationSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LocationController extends Controller
{
    public function index(Request $request)
    {
        // Добавляем жадную загрузку фотографий и категорий.
        // Публично показываем только фотографии, прошедшие модерацию.
        $query = Location::with(['city', 'categories', 'photos' => fn ($q) => $q->approved()])
            ->where('status', LocationStatus::Approved);

        if ($request->has('city_id')) {
            $query->where('city_id', $request->input('city_id'));
        }

        // Фильтрация по категориям
        if ($request->has('category_ids')) {
            $categoryIds = is_array($request->category_ids)
                ? $request->category_ids
                : explode(',', $request->category_ids);

            $query->whereHas('categories', function ($q) use ($categoryIds) {
                $q->whereIn('categories.id', $categoryIds);
            });
        }

        return $query->get();
    }

    public function show($id)
    {
        // Находим локацию с ее фотографиями и категориями
        // findOrFail автоматически вернет 404, если локация не найдена
        $location = Location::with([
            'categories',
            'photos' => fn ($q) => $q->approved()->with('user.photographerProfile'),
        ])
            ->where('status', LocationStatus::Approved)
            ->findOrFail($id);

        return $location;
    }

    public function getLocationsByBounds(Request $request)
    {
        // Валидация, что все 4 параметра пришли
        $request->validate([
            'sw_lat' => 'required|numeric',
            'sw_lng' => 'required|numeric',
            'ne_lat' => 'required|numeric',
            'ne_lng' => 'required|numeric',
        ]);

        // Границы карты могут прийти в обратном порядке (пересечение антимеридиана
        // или вывернутая область), поэтому нормализуем диапазоны.
        $southLat = min((float) $request->sw_lat, (float) $request->ne_lat);
        $northLat = max((float) $request->sw_lat, (float) $request->ne_lat);
        $westLng = min((float) $request->sw_lng, (float) $request->ne_lng);
        $eastLng = max((float) $request->sw_lng, (float) $request->ne_lng);

        $query = Location::with(['categories', 'photos' => fn ($q) => $q->approved()])
            ->where('status', LocationStatus::Approved)
            ->whereBetween('latitude', [$southLat, $northLat])
            ->whereBetween('longitude', [$westLng, $eastLng]);

        // Фильтрация по категориям
        if ($request->has('category_ids')) {
            $categoryIds = is_array($request->category_ids)
                ? $request->category_ids
                : explode(',', $request->category_ids);

            $query->whereHas('categories', function ($q) use ($categoryIds) {
                $q->whereIn('categories.id', $categoryIds);
            });
        }

        $locations = $query->orderByDesc('created_at')->limit(100)->get();

        return response()->json($locations);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'city_id' => 'required|exists:cities,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'photos' => 'nullable|array|max:10',
            // Только растровые изображения, без SVG, до 20 МБ каждое
            'photos.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:20480',
        ]);

        $settings = app(ModerationSettings::class);
        $needsModeration = $settings->location_moderation_enabled;
        $photosNeedModeration = $settings->photo_moderation_enabled;

        $status = $needsModeration ? LocationStatus::Pending : LocationStatus::Approved;
        $photoStatus = $photosNeedModeration ? PhotoStatus::Pending : PhotoStatus::Approved;

        $location = Location::create([
            'name' => $request->name,
            'description' => $request->description,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'city_id' => $request->city_id,
            'user_id' => Auth::id(),
            'status' => $status, // Устанавливаем статус в зависимости от настроек модерации
        ]);

        // Прикрепляем категории, если они были переданы
        if ($request->has('category_ids')) {
            $location->categories()->sync($request->category_ids);
        }

        if ($request->hasFile('photos')) {
            $photoStorage = app(PhotoStorage::class);

            foreach ($request->file('photos') as $photoFile) {
                $photoStorage->store($photoFile, $location->id, Auth::id(), $photoStatus);
            }
        }

        // Возвращаем локацию и флаги модерации для фронтенда
        return response()->json([
            'location' => $location->load(['categories', 'photos' => fn ($q) => $q->approved()]),
            'needs_moderation' => $needsModeration,
            'photos_need_moderation' => $photosNeedModeration,
        ], 201);
    }
}
