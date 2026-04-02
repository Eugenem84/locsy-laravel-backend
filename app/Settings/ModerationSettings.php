<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ModerationSettings extends Settings
{
    public bool $location_moderation_enabled = false;

    public static function group(): string
    {
        return 'moderation';
    }
}
