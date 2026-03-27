<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\App;

class Admin1Code extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_code',
        'admin1_code',
        'name',
    ];

    public function cities()
    {
        return $this->hasMany(City::class, 'admin1_code', 'admin1_code')
                    ->where('country_code', $this->country_code);
    }

    /**
     * Get the translations for the admin1 code.
     */
    public function translations(): HasMany
    {
        return $this->hasMany(Admin1CodeTranslation::class);
    }

    /**
     * Get the translated name of the region.
     */
    protected function translatedName(): Attribute
    {
        return Attribute::make(
            get: function () {
                $locale = App::getLocale();
                $translation = $this->translations()->where('locale', $locale)->first();

                return $translation->name ?? $this->name;
            }
        );
    }
}
