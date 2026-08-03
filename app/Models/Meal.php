<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meal extends Model
{
    protected $fillable = ['meal_date', 'meal_type', 'name', 'notes'];

    protected function casts(): array
    {
        return ['meal_date' => 'date'];
    }
}
