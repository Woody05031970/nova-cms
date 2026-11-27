<?php

namespace App\Providers;

use App\Ai\AiManager;
use App\Models\Page;
use App\Models\Setting;
use App\Plugins\Hooks;
use App\Plugins\PluginManager;
use App\Support\Install\InstallState;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Hooks::class);
        $this->app->singleton(PluginManager::class);
        $this->app->singleton(AiManager::class);

        if (InstallState::isInstalled()) {
            $this->app->make(PluginManager::class)->registerEnabled();
        }
    }

    public function boot(): void
    {
        if (! InstallState::isInstalled()) {
            return;
        }

        $this->registerCoreHints();
        $this->app->make(PluginManager::class)->bootEnabled();

        View::composer(['layouts.admin', 'admin.*', 'site.*'], function ($view) {
            $view->with('siteName', Setting::get('site_name', 'Meine Website'));
        });
    }

    /**
     * Hinweise des Kerns: was auf der Website noch fehlt.
     */
    private function registerCoreHints(): void
    {
        $hooks = $this->app->make(Hooks::class);
        $profile = url('admin/einstellungen');

        $hooks->addHint('Website-Name festlegen', 'Der Name erscheint im Browser-Tab und in Google.',
            fn () => Setting::get('site_name') !== null, $profile, 'Grundlagen');
        $hooks->addHint('Kurzbeschreibung ergänzen', 'Ein Satz, was Sie anbieten. Er wird für Google und die KI genutzt.',
            fn () => Setting::get('tagline') !== null, $profile, 'SEO');
        $hooks->addHint('Firmenname und Anschrift eintragen', 'Pflichtangaben für das Impressum.',
            fn () => Setting::get('company_name') !== null && Setting::get('street') !== null && Setting::get('zip_city') !== null, $profile, 'Rechtliches');
        $hooks->addHint('Kontakt-E-Mail und Telefon eintragen', 'Besucher und das Impressum brauchen einen schnellen Kontaktweg.',
            fn () => Setting::get('contact_email') !== null && Setting::get('phone') !== null, $profile, 'Rechtliches');
        $hooks->addHint('Branche angeben', 'Damit kann die KI später passende Seiten und Texte vorschlagen.',
            fn () => Setting::get('industry') !== null, $profile, 'KI');

        $hooks->addHint('KI-Anbieter einrichten', 'Dann schreibt die KI Texte, Google-Beschreibungen und Ihre ersten Seiten.',
            fn () => $this->app->make(AiManager::class)->isConfigured(), url('admin/ki'), 'KI');

        $pages = url('admin/seiten');
        $hooks->addHint('Startseite veröffentlichen', 'Ohne Startseite sehen Besucher nur einen Platzhalter.',
            fn () => Page::published()->where('is_home', true)->exists(), $pages, 'Inhalt');
        $hooks->addHint('Impressum veröffentlichen', 'In Deutschland Pflicht für fast jede geschäftliche Website. Es gibt eine Vorlage unter "Neue Seite".',
            fn () => Page::published()->where('purpose', 'imprint')->exists(), $pages, 'Rechtliches');
        $hooks->addHint('Datenschutzerklärung veröffentlichen', 'Pflicht, sobald Besucherdaten verarbeitet werden, also praktisch immer.',
            fn () => Page::published()->where('purpose', 'privacy')->exists(), $pages, 'Rechtliches');
        $hooks->addHint('Beschreibungen für Google ergänzen', 'Jede veröffentlichte Seite braucht einen kurzen Text für die Suchergebnisse.',
            fn () => Page::published()->exists() && ! Page::published()->where(fn ($q) => $q->whereNull('meta_description')->orWhere('meta_description', ''))->exists(),
            $pages, 'SEO');
    }
}
