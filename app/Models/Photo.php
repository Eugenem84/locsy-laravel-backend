<?php

namespace App\Models;

use App\Enums\PhotoStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Photo extends Model
{
    use HasFactory;

    protected $table = 'photos';

    protected $fillable = [
        'location_id',
        'path',
        'is_main',
        'user_id',
        'status',
        'moderation_note',
    ];

    protected $casts = [
        'status' => PhotoStatus::class,
    ];

    protected $appends = ['full_url'];

    /**
     * Только фотографии, прошедшие модерацию.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', PhotoStatus::Approved);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Аксессор для получения полного URL-адреса фотографии.
     * Используем asset() для генерации абсолютного URL.
     */
    protected function fullUrl(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                $path = $attributes['path'];
                // Проверяем, является ли путь уже полным URL
                if (Str::startsWith($path, ['http://', 'https://'])) {
                    return $path;
                }

                // Если нет, создаем абсолютный URL с помощью asset()
                return asset('storage/'.$path);
            }
        );
    }
}
