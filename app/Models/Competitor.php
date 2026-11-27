<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Competitor extends Model
{
    protected $fillable = ['url', 'name', 'data', 'error', 'analyzed_at'];

    protected function casts(): array
    {
        return ['data' => 'array', 'analyzed_at' => 'datetime'];
    }

    public function host(): string
    {
        return preg_replace('/^www\./', '', (string) parse_url($this->url, PHP_URL_HOST));
    }

    public function label(): string
    {
        return $this->name ?: $this->host();
    }
}
