<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Throwable; // Import Throwable

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

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'access_token' => $token,
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * Handle a login request.
     */
    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required',
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            if (!Auth::attempt($request->only('email', 'password'))) {
                throw ValidationException::withMessages([
                    'email' => [__('auth.failed')],
                ]);
            }

            $request->session()->regenerate();

            $user = Auth::user();
            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'message' => 'Logged in successfully',
                'user' => $user,
                'access_token' => $token,
                'token' => $token,
                'token_type' => 'Bearer',
            ]);
        } catch (ValidationException $e) {
            // Re-throw validation exceptions to be handled by Laravel's exception handler
            throw $e;
        } catch (Throwable $e) {
            // Catch any other unexpected errors
            // Log the error for debugging purposes in production
             \Log::error("Login error: " . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'message' => 'An unexpected error occurred during login. Please try again later.',
                // In development, you might want to include more details for debugging:
                 'error' => $e->getMessage(),
                 'trace' => $e->getTraceAsString(),
            ], 500);
        }
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

    public function updateAvatar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'avatar' => ['required', 'image', 'max:100000'], // 100MB Max
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $user = Auth::user();

        if ($request->hasFile('avatar')) {
            // Delete old avatar if it exists
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            $avatar = $request->file('avatar');
            $filename = time() . '.' . $avatar->getClientOriginalExtension();

            // Create an image manager with the GD driver
            $manager = new ImageManager(new Driver());
            $image = $manager->read($avatar->getRealPath());

            // Crop and resize
            $width = $image->width();
            $height = $image->height();
            $size = min($width, $height);
            $image->crop($size, $size)->resize(300, 300);

            $path = 'avatars/' . $filename;
            Storage::disk('public')->put($path, $image->encode());


            $user->avatar = $path; // Save the relative path
            $user->save();
        }

        return response()->json([
            'message' => 'Avatar updated successfully',
            'user' => $user->fresh() // Return the updated user object
        ]);
    }

    public function user(Request $request)
    {
        $user = Auth::user()->load('photographerProfile');
        return response()->json($user);
    }
}
