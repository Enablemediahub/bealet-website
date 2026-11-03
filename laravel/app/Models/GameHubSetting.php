<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameHubSetting extends Model
{
    public $incrementing = false;

    protected $table = 'gamehub_settings';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
