<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'replied' => 'boolean',
            'replied_at' => 'datetime',
        ];
    }
}
