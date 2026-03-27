<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Admin1CodeTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin1_code_id',
        'locale',
        'name',
    ];

    /**
     * Get the admin1 code that owns the translation.
     */
    public function admin1Code()
    {
        return $this->belongsTo(Admin1Code::class);
    }
}
