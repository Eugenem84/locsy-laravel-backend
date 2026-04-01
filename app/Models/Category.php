<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * Get all of the locations that are assigned this category.
     */
    public function locations()
    {
        return $this->morphedByMany(Location::class, 'categorizable');
    }
}
