<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
            'reminder_sent' => 'boolean',
        ];
    }
}
