<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'latitude',
        'longitude',
        'geonameid',
        'asciiname',
        'alternatenames',
        'feature_class',
        'feature_code',
        'country_code',
        'cc2',
        'admin1_code',
        'admin2_code',
        'population',
        'timezone',
        'modification_date',
    ];

    /**
     * Get the region associated with the city.
     */
    public function region()
    {
        return $this->belongsTo(Admin1Code::class, 'admin1_code', 'admin1_code')
                    ->where('country_code', $this->country_code);
    }


    protected static function boot()
    {
        parent::boot();

        static::creating(function ($city) {
            if (empty($city->slug)) {
                $city->slug = Str::slug($city->name);
            }
        });
    }
}
