<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShoppingItem extends Model
{
    protected $fillable = ['house_id', 'name', 'quantity', 'category', 'purchased_at'];

    protected static function booted(): void
    {
        static::creating(function (ShoppingItem $item) {
            $item->house_id ??= auth()->user()?->house_id;
        });
    }

    protected function casts(): array
    {
        return ['purchased_at' => 'datetime'];
    }
}
