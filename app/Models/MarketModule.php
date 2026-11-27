<?php

namespace App\Models;

use App\Shop\Money;
use Illuminate\Database\Eloquent\Model;

/** Ein Modul im Marktplatz des Lizenzservers */
class MarketModule extends Model
{
    public const CATEGORIES = [
        'inhalt' => 'Inhalte', 'shop' => 'Shop', 'marketing' => 'Marketing', 'seo' => 'SEO',
        'sicherheit' => 'Sicherheit', 'ki' => 'KI', 'schnittstellen' => 'Schnittstellen',
    ];

    protected $fillable = ['slug', 'name', 'description', 'category', 'version', 'requires', 'is_paid', 'price_cents', 'buy_url', 'file', 'sha256', 'is_published', 'downloads'];

    protected function casts(): array
    {
        return ['is_paid' => 'boolean', 'is_published' => 'boolean', 'price_cents' => 'integer'];
    }

    public function path(): string
    {
        return storage_path('app/marketplace/'.$this->file);
    }

    public function priceLabel(): string
    {
        return $this->is_paid ? ($this->price_cents ? Money::format($this->price_cents) : 'kostenpflichtig') : 'kostenlos';
    }
}
