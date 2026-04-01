<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'country_code',
        'admin1_code',
        'latitude',
        'longitude',
        'population',
        'alternatenames',
        'asciiname',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
