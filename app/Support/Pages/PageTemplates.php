<?php

namespace App\Support\Pages;

use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Vorlagen für neue Seiten, damit niemand vor einer leeren Fläche sitzt.
 */
class PageTemplates
{
    /** @return array<string, array{label: string, description: string, icon: string}> */
    public static function all(): array
    {
        return [
            'leer' => ['label' => 'Leere Seite', 'description' => 'Sie starten mit einem Textblock.', 'icon' => 'pages'],
            'start' => ['label' => 'Startseite', 'description' => 'Titelbild, Begrüßung und Kontakt-Knopf.', 'icon' => 'home'],
            'ueber' => ['label' => 'Über uns', 'description' => 'Wer Sie sind und was Sie auszeichnet.', 'icon' => 'sparkles'],
            'kontakt' => ['label' => 'Kontakt', 'description' => 'Adresse, Telefon und E-Mail aus Ihrem Firmenprofil.', 'icon' => 'clock'],
            'impressum' => ['label' => 'Impressum', 'description' => 'Wird aus Ihrem Firmenprofil befüllt.', 'icon' => 'shield'],
            'datenschutz' => ['label' => 'Datenschutzerklärung', 'description' => 'Grundgerüst zum Anpassen.', 'icon' => 'shield'],
        ];
    }

    /** @return array{title: string, blocks: list<array<string, mixed>>, purpose?: string, is_home?: bool, show_in_menu?: bool} */
    public static function make(string $key): array
    {
        $s = fn (string $k, string $fallback = '') => e(Setting::get($k, $fallback));
        $name = $s('company_name', Setting::get('site_name', 'Ihr Unternehmen'));
        $id = fn () => Str::lower(Str::random(10));
        $text = fn (string $html) => ['id' => $id(), 'type' => 'text', 'data' => ['html' => $html]];

        $address = collect([$s('street'), $s('zip_city')])->filter()->implode('<br>');
        $contact = collect([
            Setting::get('phone') ? 'Telefon: '.$s('phone') : null,
            Setting::get('contact_email') ? 'E-Mail: <a href="mailto:'.$s('contact_email').'">'.$s('contact_email').'</a>' : null,
        ])->filter()->implode('<br>');
        $missing = '<em>[bitte im Firmenprofil ergänzen]</em>';

        return match ($key) {
            'start' => [
                'title' => 'Startseite',
                'is_home' => true,
                'blocks' => [
                    ['id' => $id(), 'type' => 'hero', 'data' => [
                        'title' => Setting::get('site_name', 'Willkommen'),
                        'text' => Setting::get('tagline', 'Schön, dass Sie hier sind.'),
                        'image' => null, 'button_label' => 'Kontakt aufnehmen', 'button_url' => '/kontakt',
                    ]],
                    $text('<h2>Willkommen</h2><p>Erzählen Sie hier in zwei, drei Sätzen, was Sie anbieten und warum Kunden zu Ihnen kommen sollten.</p>'),
                ],
            ],
            'ueber' => [
                'title' => 'Über uns',
                'blocks' => [$text('<h2>Wer wir sind</h2><p>Stellen Sie sich vor: Seit wann gibt es Sie, was ist Ihnen wichtig, was macht Sie besonders?</p><h3>Unser Team</h3><p>Ein paar Worte zu den Menschen hinter '.$name.'.</p>')],
            ],
            'kontakt' => [
                'title' => 'Kontakt',
                'blocks' => [$text('<h2>So erreichen Sie uns</h2><p><strong>'.$name.'</strong><br>'.($address ?: $missing).'</p><p>'.($contact ?: $missing).'</p>')],
            ],
            'impressum' => [
                'title' => 'Impressum',
                'purpose' => 'imprint',
                'show_in_menu' => false,
                'blocks' => [$text('<h2>Angaben gemäß § 5 DDG</h2><p>'.$name.'<br>'.($address ?: $missing).'</p><h3>Kontakt</h3><p>'.($contact ?: $missing).'</p><h3>Vertreten durch</h3><p>[Name der Inhaberin / des Inhabers oder der Geschäftsführung]</p><h3>Registereintrag und Umsatzsteuer-ID</h3><p>[falls vorhanden: Registergericht, Registernummer, USt-IdNr.]</p><p><em>Hinweis: Diese Vorlage ersetzt keine Rechtsberatung. Bitte prüfen Sie, welche Angaben für Ihre Rechtsform nötig sind.</em></p>')],
            ],
            'datenschutz' => [
                'title' => 'Datenschutzerklärung',
                'purpose' => 'privacy',
                'show_in_menu' => false,
                'blocks' => [$text('<h2>Datenschutz auf einen Blick</h2><p>Verantwortlich für die Datenverarbeitung auf dieser Website ist:<br>'.$name.'<br>'.($address ?: $missing).'<br>'.($contact ?: $missing).'</p><h3>Hosting</h3><p>[Name und Anschrift Ihres Hosters, z. B. IONOS oder Strato]</p><h3>Server-Logdateien</h3><p>Beim Aufruf der Website werden technisch notwendige Daten (z. B. IP-Adresse, Uhrzeit, Browser) verarbeitet, um die Seite sicher auszuliefern.</p><h3>Eingebettete Videos</h3><p>Videos von YouTube oder Vimeo werden erst nach Ihrem Klick geladen. Erst dann werden Daten an den Anbieter übertragen.</p><h3>Ihre Rechte</h3><p>Sie haben das Recht auf Auskunft, Berichtigung, Löschung und Einschränkung der Verarbeitung sowie auf Beschwerde bei einer Aufsichtsbehörde.</p><p><em>Hinweis: Diese Vorlage ist ein Grundgerüst und ersetzt keine Rechtsberatung.</em></p>')],
            ],
            default => ['title' => 'Neue Seite', 'blocks' => [$text('<p></p>')]],
        };
    }
}
