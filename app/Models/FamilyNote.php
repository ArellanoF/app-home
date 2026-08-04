<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FamilyNote extends Model
{
    protected $fillable = ['house_id', 'user_id', 'content'];

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
