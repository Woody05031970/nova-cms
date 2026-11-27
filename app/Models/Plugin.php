<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plugin extends Model
{
    protected $fillable = ['slug', 'name', 'version', 'description', 'author', 'enabled', 'last_error'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
