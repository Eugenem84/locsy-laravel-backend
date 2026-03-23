<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
