<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    protected $fillable = ['email', 'source', 'confirmed_at'];

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime'];
    }
}
