<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FavoriteController extends Controller
{
    public function add(Request $request, Location $location)
    {
        $request->user()->favorites()->attach($location);

        return response()->json(['status' => 'added']);
    }

    public function remove(Request $request, Location $location)
    {
        $request->user()->favorites()->detach($location);

        return response()->json(['status' => 'removed']);
    }

    public function list(Request $request)
    {
        $favorites = $request->user()->favorites()->with('photos')->get();

        return response()->json($favorites);
    }
}
