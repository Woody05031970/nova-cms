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
            'agb' => ['label' => 'AGB (Shop)', 'description' => 'Allgemeine Geschäftsbedingungen für Ihren Online-Shop.', 'icon' => 'cart'],
            'widerruf' => ['label' => 'Widerrufsbelehrung (Shop)', 'description' => 'Gesetzliches Muster mit Widerrufsformular.', 'icon' => 'cart'],
            'versand' => ['label' => 'Versand und Zahlung (Shop)', 'description' => 'Aus Ihren Shop-Einstellungen befüllt.', 'icon' => 'cart'],
        ];
    }

    private static function shippingText(): string
    {
        $countries = collect(\App\Shop\ShopSettings::shippingCountries())->map(fn ($c) => \App\Shop\ShopSettings::COUNTRIES[$c]['label'])->implode(', ');
        $free = \App\Shop\ShopSettings::freeShippingFromCents();
        $methods = collect(app(\App\Shop\Payments\PaymentRegistry::class)->available())->map(fn ($m) => '<li>'.e($m->label()).'</li>')->implode('');

        return '<h2>Versandkosten</h2><p>Wir liefern nach: '.e($countries).'.</p>'
            .'<p>Die Versandkosten betragen '.e(\App\Shop\Money::format(\App\Shop\ShopSettings::shippingCents())).' je Bestellung'
            .($free !== null ? '. Ab einem Bestellwert von '.e(\App\Shop\Money::format($free)).' liefern wir versandkostenfrei' : '').'.</p>'
            .'<h2>Lieferzeit</h2><p>Sofern beim Produkt nicht anders angegeben: '.e(\App\Shop\ShopSettings::deliveryTime()).'. Bei Vorkasse beginnt die Lieferzeit nach Zahlungseingang.</p>'
            .'<h2>Zahlungsarten</h2>'.($methods ? '<ul>'.$methods.'</ul>' : '<p>[Bitte zuerst Zahlungsarten in den Shop-Einstellungen einrichten]</p>');
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
            'agb' => [
                'title' => 'AGB',
                'purpose' => 'terms',
                'show_in_menu' => false,
                'blocks' => [$text('<h2>Allgemeine Geschäftsbedingungen</h2>'
                    .'<h3>§ 1 Geltungsbereich</h3><p>Diese Geschäftsbedingungen gelten für alle Bestellungen über unseren Online-Shop. Vertragspartner ist '.$name.', '.($address ? strip_tags(str_replace('<br>', ', ', $address)) : $missing).'.</p>'
                    .'<h3>§ 2 Vertragsschluss</h3><p>Die Darstellung der Produkte im Shop ist kein verbindliches Angebot. Mit Klick auf „Zahlungspflichtig bestellen“ geben Sie eine verbindliche Bestellung ab. Wir bestätigen den Eingang per E-Mail; der Vertrag kommt mit dieser Bestätigung zustande.</p>'
                    .'<h3>§ 3 Preise und Versandkosten</h3><p>Alle Preise sind Endpreise inklusive der gesetzlichen Mehrwertsteuer. Hinzu kommen Versandkosten, die im Shop und vor Abschluss der Bestellung angezeigt werden.</p>'
                    .'<h3>§ 4 Zahlung</h3><p>Die verfügbaren Zahlungsarten werden in der Kasse angezeigt. Bei Vorkasse versenden wir nach Zahlungseingang.</p>'
                    .'<h3>§ 5 Lieferung</h3><p>Die Lieferzeit ist beim jeweiligen Produkt angegeben.</p>'
                    .'<h3>§ 6 Eigentumsvorbehalt</h3><p>Die Ware bleibt bis zur vollständigen Bezahlung unser Eigentum.</p>'
                    .'<h3>§ 7 Widerrufsrecht</h3><p>Verbrauchern steht ein Widerrufsrecht zu. Einzelheiten finden Sie in unserer Widerrufsbelehrung.</p>'
                    .'<h3>§ 8 Gewährleistung</h3><p>Es gilt das gesetzliche Mängelhaftungsrecht.</p>'
                    .'<h3>§ 9 Streitbeilegung</h3><p>Wir sind nicht bereit und nicht verpflichtet, an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.</p>'
                    .'<p><em>Hinweis: Diese Vorlage ist ein Ausgangspunkt und ersetzt keine Rechtsberatung. Bitte prüfen und ergänzen Sie sie für Ihr Geschäft.</em></p>')],
            ],
            'widerruf' => [
                'title' => 'Widerrufsbelehrung',
                'purpose' => 'withdrawal',
                'show_in_menu' => false,
                'blocks' => [$text('<h2>Widerrufsrecht</h2>'
                    .'<p>Sie haben das Recht, binnen vierzehn Tagen ohne Angabe von Gründen diesen Vertrag zu widerrufen.</p>'
                    .'<p>Die Widerrufsfrist beträgt vierzehn Tage ab dem Tag, an dem Sie oder ein von Ihnen benannter Dritter, der nicht der Beförderer ist, die Waren in Besitz genommen haben bzw. hat.</p>'
                    .'<p>Um Ihr Widerrufsrecht auszuüben, müssen Sie uns ('.$name.', '.($address ? strip_tags(str_replace('<br>', ', ', $address)) : $missing).($contact ? ', '.strip_tags(str_replace('<br>', ', ', $contact)) : '').') mittels einer eindeutigen Erklärung (z. B. ein mit der Post versandter Brief oder E-Mail) über Ihren Entschluss, diesen Vertrag zu widerrufen, informieren. Sie können dafür das beigefügte Muster-Widerrufsformular verwenden, das jedoch nicht vorgeschrieben ist.</p>'
                    .'<p>Zur Wahrung der Widerrufsfrist reicht es aus, dass Sie die Mitteilung über die Ausübung des Widerrufsrechts vor Ablauf der Widerrufsfrist absenden.</p>'
                    .'<h3>Folgen des Widerrufs</h3>'
                    .'<p>Wenn Sie diesen Vertrag widerrufen, haben wir Ihnen alle Zahlungen, die wir von Ihnen erhalten haben, einschließlich der Lieferkosten (mit Ausnahme der zusätzlichen Kosten, die sich daraus ergeben, dass Sie eine andere Art der Lieferung als die von uns angebotene, günstigste Standardlieferung gewählt haben), unverzüglich und spätestens binnen vierzehn Tagen ab dem Tag zurückzuzahlen, an dem die Mitteilung über Ihren Widerruf dieses Vertrags bei uns eingegangen ist. Für diese Rückzahlung verwenden wir dasselbe Zahlungsmittel, das Sie bei der ursprünglichen Transaktion eingesetzt haben, es sei denn, mit Ihnen wurde ausdrücklich etwas anderes vereinbart; in keinem Fall werden Ihnen wegen dieser Rückzahlung Entgelte berechnet.</p>'
                    .'<p>Wir können die Rückzahlung verweigern, bis wir die Waren wieder zurückerhalten haben oder bis Sie den Nachweis erbracht haben, dass Sie die Waren zurückgesandt haben, je nachdem, welches der frühere Zeitpunkt ist.</p>'
                    .'<p>Sie haben die Waren unverzüglich und in jedem Fall spätestens binnen vierzehn Tagen ab dem Tag, an dem Sie uns über den Widerruf dieses Vertrags unterrichten, an uns zurückzusenden oder zu übergeben. Die Frist ist gewahrt, wenn Sie die Waren vor Ablauf der Frist von vierzehn Tagen absenden.</p>'
                    .'<p>Sie tragen die unmittelbaren Kosten der Rücksendung der Waren.</p>'
                    .'<p>Sie müssen für einen etwaigen Wertverlust der Waren nur aufkommen, wenn dieser Wertverlust auf einen zur Prüfung der Beschaffenheit, Eigenschaften und Funktionsweise der Waren nicht notwendigen Umgang mit ihnen zurückzuführen ist.</p>'
                    .'<h3>Muster-Widerrufsformular</h3>'
                    .'<p>(Wenn Sie den Vertrag widerrufen wollen, dann füllen Sie bitte dieses Formular aus und senden Sie es zurück.)</p>'
                    .'<p>An '.$name.', '.($address ? strip_tags(str_replace('<br>', ', ', $address)) : $missing).(Setting::get('contact_email') ? ', E-Mail: '.$s('contact_email') : '').':</p>'
                    .'<p>Hiermit widerrufe(n) ich/wir (*) den von mir/uns (*) abgeschlossenen Vertrag über den Kauf der folgenden Waren (*)</p>'
                    .'<p>Bestellt am (*) / erhalten am (*)<br>Name des/der Verbraucher(s)<br>Anschrift des/der Verbraucher(s)<br>Unterschrift des/der Verbraucher(s) (nur bei Mitteilung auf Papier)<br>Datum</p>'
                    .'<p>(*) Unzutreffendes streichen.</p>'
                    .'<p><em>Hinweis: Gesetzliches Muster für den Verkauf von Waren in Deutschland. Bei digitalen Inhalten, Dienstleistungen oder Ausnahmen (z. B. verderbliche Ware) sind Anpassungen nötig.</em></p>')],
            ],
            'versand' => [
                'title' => 'Versand und Zahlung',
                'purpose' => 'shipping',
                'show_in_menu' => false,
                'blocks' => [$text(self::shippingText())],
            ],
            default => ['title' => 'Neue Seite', 'blocks' => [$text('<p></p>')]],
        };
    }
}
