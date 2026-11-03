<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    protected $table = 'newsletter';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'subscribed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
