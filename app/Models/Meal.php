<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meal extends Model
{
    protected $fillable = ['house_id', 'meal_date', 'meal_type', 'name', 'notes', 'ingredients'];

    protected static function booted(): void
    {
        static::creating(function (Meal $meal) {
            $meal->house_id ??= auth()->user()?->house_id;
        });
    }

    protected function casts(): array
    {
        return ['meal_date' => 'date', 'ingredients' => 'array'];
    }
}
