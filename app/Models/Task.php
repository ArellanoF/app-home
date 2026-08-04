<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'house_id',
        'icon',
        'description',
        'user_id',
        'due_date',
        'recurrence',
        'recurrence_source_id',
        'completed_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Task $task) {
            $task->house_id ??= auth()->user()?->house_id;
        });
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }
}
