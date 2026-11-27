<?php

namespace NovaPlugins\Oeffnungszeiten;

use App\Plugins\Hooks;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use NovaPlugins\Oeffnungszeiten\Http\OeffnungszeitenController;

/**
 * Beispiel-Plugin: zeigt alle Erweiterungspunkte von Nova CMS.
 * - eigene Datenbanktabelle (database/migrations)
 * - eigene Admin-Seite mit Menüpunkt
 * - Dashboard-Kachel
 * - Hinweis "Das fehlt noch"
 */
class OeffnungszeitenServiceProvider extends ServiceProvider
{
    public function boot(Hooks $hooks): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'oeffnungszeiten');

        Route::middleware(['web', 'auth', 'admin'])->prefix('admin/oeffnungszeiten')->group(function () {
            Route::get('/', [OeffnungszeitenController::class, 'edit'])->name('oeffnungszeiten.edit');
            Route::put('/', [OeffnungszeitenController::class, 'update'])->name('oeffnungszeiten.update');
        });

        $hooks->addMenuItem('Öffnungszeiten', url('admin/oeffnungszeiten'), 'clock', 25, 'oeffnungszeiten.*');

        $hooks->addDashboardWidget('Öffnungszeiten', fn () => view('oeffnungszeiten::widget', [
            'tage' => OeffnungszeitenController::load(),
        ])->render(), 20);

        $hooks->addHint(
            'Öffnungszeiten eintragen',
            'Kunden suchen sie als Erstes. Google zeigt sie direkt in den Suchergebnissen.',
            fn () => Schema::hasTable('oeffnungszeiten') && DB::table('oeffnungszeiten')->where('geschlossen', false)->exists(),
            url('admin/oeffnungszeiten'),
            'Inhalt',
        );
    }
}
