<?php

namespace App\Providers;

use App\Ai\AiManager;
use App\Licensing\HttpTransport;
use App\Licensing\License;
use App\Licensing\LicenseIssuer;
use App\Licensing\LicenseServer;
use App\Licensing\LocalTransport;
use App\Licensing\Transport;
use App\Models\Media;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Plugins\Hooks;
use App\Plugins\PluginManager;
use App\Shop\Payments\PaymentRegistry;
use App\Shop\ShopSettings;
use App\Support\Install\InstallState;
use App\Support\Security\Scanner;
use Carbon\Carbon;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Lizenzserver: auf dem Server selbst ohne Umweg über HTTP, sonst per HTTPS
        $this->app->bind(Transport::class, fn ($app) => LicenseServer::enabled()
            ? $app->make(LocalTransport::class)
            : $app->make(HttpTransport::class));
        $this->app->scoped(License::class);

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

        $this->applyMailSettings();
        // Auf dem Lizenzserver: Lizenzen für bezahlte Bestellungen ausgeben
        $this->app->make(Hooks::class)->addAction('shop.order.paid', function ($order) {
            if (LicenseServer::enabled()) {
                $this->app->make(LicenseIssuer::class)->forOrder($order);
            }
        });
        $this->registerCoreHints();
        $this->app->make(PluginManager::class)->bootEnabled();

        View::composer(['layouts.admin', 'admin.*', 'site.*', 'shop.*'], function ($view) {
            $view->with('siteName', Setting::get('site_name', 'Meine Website'));
        });
    }

    /**
     * E-Mail-Versand aus den Einstellungen (Postausgang des Hosters), sonst Vorgabe aus der .env.
     */
    private function applyMailSettings(): void
    {
        try {
            $mode = Setting::get('mail_mode');
        } catch (\Throwable) {
            return; // Datenbank noch nicht bereit
        }
        if ($mode === 'smtp') {
            $port = (int) Setting::get('mail_port', '587');
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => Setting::get('mail_host'),
                'mail.mailers.smtp.port' => $port,
                'mail.mailers.smtp.scheme' => $port === 465 ? 'smtps' : 'smtp',
                'mail.mailers.smtp.username' => Setting::get('mail_user'),
                'mail.mailers.smtp.password' => ShopSettings::secret('mail_password'),
            ]);
        } elseif ($mode === 'sendmail') {
            config(['mail.default' => 'sendmail']);
        }
        if ($from = Setting::get('mail_from', Setting::get('contact_email'))) {
            config(['mail.from.address' => $from, 'mail.from.name' => Setting::get('site_name', config('app.name'))]);
        }
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

        $hooks->addHint('Bildbeschreibungen ergänzen', 'Bilder ohne Beschreibung sind für Google und blinde Besucher unsichtbar. Die KI kann sie für Sie schreiben.',
            fn () => ! Media::where('kind', 'image')->where(fn ($q) => $q->whereNull('alt')->orWhere('alt', ''))->exists(), url('admin/medien'), 'SEO');

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

        // Shop: Pflichtangaben erst, wenn der Shop eingeschaltet ist (erst beim Anzeigen geprüft)
        $hooks->addFilter('dashboard.hints', function (array $hints) use ($pages) {
            if (! ShopSettings::enabled()) {
                return $hints;
            }
            foreach (['terms' => 'AGB', 'withdrawal' => 'Widerrufsbelehrung', 'shipping' => 'Seite „Versand und Zahlung“'] as $purpose => $label) {
                $hints[] = ['title' => $label.' veröffentlichen', 'help' => 'Pflicht für Online-Shops. Eine Vorlage gibt es unter Seiten › Neue Seite.',
                    'check' => fn () => Page::published()->where('purpose', $purpose)->exists(), 'url' => $pages, 'area' => 'Shop'];
            }
            $hints[] = ['title' => 'Zahlungsart einrichten', 'help' => 'Ohne Zahlungsart kann niemand bestellen. Am einfachsten: Vorkasse mit Ihrer IBAN.',
                'check' => fn () => app(PaymentRegistry::class)->available() !== [], 'url' => url('admin/shop/einstellungen'), 'area' => 'Shop'];
            $hints[] = ['title' => 'E-Mail-Versand einrichten', 'help' => 'Sonst erhalten Kunden keine Bestellbestätigung. Tragen Sie das Postfach Ihres Hosters ein.',
                'check' => fn () => Setting::get('mail_mode') !== null, 'url' => url('admin/einstellungen'), 'area' => 'Shop'];
            $hints[] = ['title' => 'Steuernummer für Rechnungen eintragen', 'help' => 'Auf jeder Rechnung muss die USt-IdNr. oder die Steuernummer stehen.',
                'check' => fn () => Setting::get('shop_vat_id') !== null, 'url' => url('admin/shop/einstellungen'), 'area' => 'Shop'];

            return $hints;
        });

        $hooks->addHint('Zwei-Faktor-Anmeldung einrichten', 'Schützt Ihr Konto, selbst wenn jemand Ihr Passwort kennt. Dauert zwei Minuten mit einer App auf dem Handy.',
            fn () => ! User::where('role', 'admin')->whereNull('two_factor_confirmed_at')->exists(), url('admin/konto'), 'Sicherheit');
        $hooks->addHint('Sicherheits-Check durchführen', 'Nova CMS prüft Einstellungen und Dateien auf Schwachstellen. Am besten einmal im Monat.',
            function () {
                $last = Scanner::last();

                return $last && now()->diffInDays(Carbon::parse($last['at']), true) <= 30;
            }, url('admin/sicherheit'), 'Sicherheit');
    }
}
