<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameHubPlayer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'total_score' => 'integer',
            'sessions' => 'integer',
            'last_played_at' => 'datetime',
        ];
    }
}
