<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class House extends Model
{
    protected $fillable = ['name', 'google_calendar_url', 'google_calendar_ical_url', 'weather_location', 'weather_latitude', 'weather_longitude'];

    protected function casts(): array
    {
        return ['weather_latitude' => 'float', 'weather_longitude' => 'float'];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
