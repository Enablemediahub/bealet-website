<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FounderGallery extends Model
{
    protected $table = 'founder_gallery';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
