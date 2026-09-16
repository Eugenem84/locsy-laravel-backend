<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function add(Request $request, Location $location)
    {
        // syncWithoutDetaching защищает от дублей при повторном добавлении
        $request->user()->favorites()->syncWithoutDetaching([$location->id]);

        return response()->json(['status' => 'added']);
    }

    public function remove(Request $request, Location $location)
    {
        $request->user()->favorites()->detach($location);

        return response()->json(['status' => 'removed']);
    }

    public function list(Request $request)
    {
        // В публичный список избранного попадают только одобренные фотографии
        $favorites = $request->user()
            ->favorites()
            ->with(['city', 'photos' => fn ($q) => $q->approved()])
            ->get();

        return response()->json($favorites);
    }
}
