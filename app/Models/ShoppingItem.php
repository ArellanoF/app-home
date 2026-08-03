<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShoppingItem extends Model
{
    protected $fillable = ['name', 'quantity', 'category', 'purchased_at'];

    protected function casts(): array
    {
        return ['purchased_at' => 'datetime'];
    }
}
