<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Handle a registration request.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'city_id' => ['required', 'exists:cities,id'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'city_id' => $request->city_id,
        ]);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user
        ], 201);
    }

    /**
     * Handle a login request.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();
        if ($user->city) {
            $request->session()->put('city', $user->city);
        }

        return response()->noContent();
    }

    /**
     * Handle a logout request.
     */
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function updateUserCity(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'city_id' => ['required', 'exists:cities,id'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $user = Auth::user();
        $user->city_id = $request->city_id;
        $user->save();

        return response()->json([
            'message' => 'User city updated successfully',
            'user' => $user
        ]);
    }

    public function user(Request $request)
    {
        $user = Auth::user()->load('photographerProfile');
        return response()->json($user);
    }
}
