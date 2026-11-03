<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameHubAttempt extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean'];
    }
}
