<?php

namespace App\Http\Controllers\Api;

use App\Enums\PhotoStatus;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\PhotographerProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

class AuthController extends Controller
{
    /**
     * Регистрация. На входе пользователь выбирает, кто он: «пользователь» или
     * «фотограф». Для фотографа сразу создаётся профиль с портфолио-настройками,
     * чтобы он мог скидывать клиентам ссылку на свою страницу.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'city_id' => ['required', 'exists:cities,id'],
            'role' => ['nullable', 'in:user,photographer'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'work_types' => ['nullable', 'array'],
            'work_types.*' => ['string', 'max:100'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'telegram' => ['nullable', 'string', 'max:255'],
            'vk' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $isPhotographer = $request->input('role') === 'photographer';

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'city_id' => $request->city_id,
            'is_photographer' => $isPhotographer,
        ]);

        if ($isPhotographer) {
            PhotographerProfile::create([
                'user_id' => $user->id,
                'display_name' => $request->input('display_name') ?: $user->name,
                'description' => $request->input('description'),
                'city_id' => $request->city_id,
                'work_types' => $request->input('work_types', []),
                'instagram' => $request->input('instagram'),
                'telegram' => $request->input('telegram'),
                'vk' => $request->input('vk'),
                'website' => $request->input('website'),
                'is_active' => true,
            ]);
        }

        // Логиним пользователя сразу после регистрации
        Auth::login($user);
        $request->session()->regenerate();

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user->fresh()->load('photographerProfile'),
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

            if (! Auth::attempt($request->only('email', 'password'))) {
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
            // Технические детали пишем только в лог, клиенту — нейтральное сообщение
            Log::error('Login error: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'message' => 'An unexpected error occurred during login. Please try again later.',
            ], 500);
        }
    }

    /**
     * Handle a logout request.
     */
    public function logout(Request $request)
    {
        // Отзываем Bearer-токен, если запрос авторизован именно им
        $token = $request->user()?->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

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
            'user' => $user,
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
            $filename = time().'.'.$avatar->getClientOriginalExtension();

            // Create an image manager with the GD driver
            $manager = new ImageManager(new Driver);
            $image = $manager->read($avatar->getRealPath());

            // Crop and resize
            $width = $image->width();
            $height = $image->height();
            $size = min($width, $height);
            $image->crop($size, $size)->resize(300, 300);

            $path = 'avatars/'.$filename;
            Storage::disk('public')->put($path, $image->encode());

            $user->avatar = $path; // Save the relative path
            $user->save();
        }

        return response()->json([
            'message' => 'Avatar updated successfully',
            'user' => $user->fresh(), // Return the updated user object
        ]);
    }

    /**
     * Сохранение профиля фотографа самим пользователем (или апгрейд из «пользователя»).
     * Это заготовка под будущее платное размещение: профиль есть только у фотографов.
     */
    public function updatePhotographerProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'display_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'work_types' => ['nullable', 'array'],
            'work_types.*' => ['string', 'max:100'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'telegram' => ['nullable', 'string', 'max:255'],
            'vk' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $user = $request->user();

        $user->photographerProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'display_name' => $request->input('display_name') ?: $user->name,
                'description' => $request->input('description'),
                'city_id' => $request->input('city_id', $user->city_id),
                'work_types' => $request->input('work_types', []),
                'instagram' => $request->input('instagram'),
                'telegram' => $request->input('telegram'),
                'vk' => $request->input('vk'),
                'website' => $request->input('website'),
                'is_active' => true,
            ]
        );

        $user->is_photographer = true;
        $user->save();

        return response()->json([
            'message' => 'Профиль фотографа сохранён',
            'user' => $user->fresh()->load('photographerProfile'),
        ]);
    }

    /**
     * Мои локации со статусом модерации — автор видит судьбу своей заявки.
     */
    public function myLocations(Request $request)
    {
        $locations = $request->user()
            ->locations()
            ->with(['city', 'categories', 'photos' => fn ($q) => $q->approved()])
            ->withCount([
                'photos as approved_photos_count' => fn ($q) => $q->approved(),
                'photos as pending_photos_count' => fn ($q) => $q->where('status', PhotoStatus::Pending),
            ])
            ->latest()
            ->get();

        return response()->json($locations);
    }

    /**
     * Мои фотографии со статусом модерации и причиной отказа.
     */
    public function myPhotos(Request $request)
    {
        $photos = $request->user()
            ->photos()
            ->with('location:id,name,city_id')
            ->latest()
            ->get()
            ->map(fn (Photo $photo) => [
                'id' => $photo->id,
                'full_url' => $photo->full_url,
                'status' => $photo->status,
                'moderation_note' => $photo->moderation_note,
                'created_at' => $photo->created_at,
                'location' => $photo->location ? [
                    'id' => $photo->location->id,
                    'name' => $photo->location->name,
                ] : null,
            ]);

        return response()->json($photos);
    }

    public function user(Request $request)
    {
        $user = Auth::user()->load('photographerProfile');

        return response()->json($user);
    }
}
