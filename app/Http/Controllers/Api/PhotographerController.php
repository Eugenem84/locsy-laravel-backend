<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PhotographerProfile;
use Illuminate\Http\Request;

class PhotographerController extends Controller
{
    public function show($userId)
    {
        $photographer = PhotographerProfile::with('city')->where('user_id', $userId)->firstOrFail();
        return response()->json($photographer);
    }
}
