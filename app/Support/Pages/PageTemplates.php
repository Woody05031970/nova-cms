<?php

namespace App\Support\Pages;

use App\Models\Setting;
use App\Shop\Money;
use App\Shop\Payments\PaymentRegistry;
use App\Shop\ShopSettings;
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
            'landing' => ['label' => 'Landingpage mit Video', 'description' => 'Video im Hintergrund, Vorteile, Fragen und Aufruf.', 'icon' => 'rocket'],
            'aktion' => ['label' => 'Aktion ankündigen', 'description' => 'Angebot oder Veranstaltung mit Countdown-Gefühl.', 'icon' => 'bolt'],
            'leistungen' => ['label' => 'Leistungen und Preise', 'description' => 'Schritte, Pakete und häufige Fragen.', 'icon' => 'layers'],
            'newsletter' => ['label' => 'Newsletter-Seite', 'description' => 'Gründe fürs Abonnieren und Anmeldeformular.', 'icon' => 'mail'],
            'ueber' => ['label' => 'Über uns', 'description' => 'Wer Sie sind und was Sie auszeichnet.', 'icon' => 'sparkles'],
            'kontakt' => ['label' => 'Kontakt', 'description' => 'Adresse, Telefon und E-Mail aus Ihrem Firmenprofil.', 'icon' => 'clock'],
            'impressum' => ['label' => 'Impressum', 'description' => 'Wird aus Ihrem Firmenprofil befüllt.', 'icon' => 'shield'],
            'datenschutz' => ['label' => 'Datenschutzerklärung', 'description' => 'Grundgerüst zum Anpassen.', 'icon' => 'shield'],
            'agb' => ['label' => 'AGB (Shop)', 'description' => 'Allgemeine Geschäftsbedingungen für Ihren Online-Shop.', 'icon' => 'cart'],
            'widerruf' => ['label' => 'Widerrufsbelehrung (Shop)', 'description' => 'Gesetzliches Muster mit Widerrufsformular.', 'icon' => 'cart'],
            'versand' => ['label' => 'Versand und Zahlung (Shop)', 'description' => 'Aus Ihren Shop-Einstellungen befüllt.', 'icon' => 'cart'],
            'barrierefreiheit' => ['label' => 'Erklärung zur Barrierefreiheit', 'description' => 'Pflichtangaben nach dem Barrierefreiheitsstärkungsgesetz (BFSG).', 'icon' => 'shield'],
        ];
    }

    private static function shippingText(): string
    {
        $countries = collect(ShopSettings::shippingCountries())->map(fn ($c) => ShopSettings::COUNTRIES[$c]['label'])->implode(', ');
        $free = ShopSettings::freeShippingFromCents();
        $methods = collect(app(PaymentRegistry::class)->available())->map(fn ($m) => '<li>'.e($m->label()).'</li>')->implode('');

        return '<h2>Versandkosten</h2><p>Wir liefern nach: '.e($countries).'.</p>'
            .'<p>Die Versandkosten betragen '.e(Money::format(ShopSettings::shippingCents())).' je Bestellung'
            .($free !== null ? '. Ab einem Bestellwert von '.e(Money::format($free)).' liefern wir versandkostenfrei' : '').'.</p>'
            .'<h2>Lieferzeit</h2><p>Sofern beim Produkt nicht anders angegeben: '.e(ShopSettings::deliveryTime()).'. Bei Vorkasse beginnt die Lieferzeit nach Zahlungseingang.</p>'
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
            'landing' => [
                'title' => 'Landingpage',
                'show_in_menu' => false,
                'blocks' => [
                    ['id' => $id(), 'type' => 'hero', 'data' => ['variant' => 'video', 'eyebrow' => 'Neu', 'title' => 'Ihre Botschaft in einem Satz', 'text' => 'Laden Sie im Baustein „Titelbild“ ein kurzes Video hoch. Es läuft stumm im Hintergrund, der Farbschleier hält den Text gut lesbar.', 'button_label' => 'Jetzt anfragen', 'button_url' => '/kontakt', 'button2_label' => 'Mehr erfahren', 'button2_url' => '#vorteile', 'overlay' => 'dark', 'opacity' => 55, 'height' => 'full', 'align' => 'center', 'image' => null, 'video' => null]],
                    ['id' => $id(), 'type' => 'features', 'data' => ['eyebrow' => 'Darum wir', 'title' => 'Drei gute Gründe', 'intro' => '', 'style' => 'grid', 'items' => [
                        ['icon' => 'bolt', 'title' => 'Schnell', 'text' => 'Was geht bei Ihnen schneller als anderswo?'],
                        ['icon' => 'heart', 'title' => 'Persönlich', 'text' => 'Was schätzen Ihre Kunden besonders?'],
                        ['icon' => 'shield', 'title' => 'Zuverlässig', 'text' => 'Worauf kann man sich bei Ihnen verlassen?'],
                    ]]],
                    ['id' => $id(), 'type' => 'faq', 'data' => ['eyebrow' => '', 'title' => 'Gut zu wissen', 'intro' => '', 'items' => [['q' => 'Eine häufige Frage?', 'a' => 'Die passende Antwort.'], ['q' => 'Noch eine Frage?', 'a' => 'Noch eine Antwort.']]]],
                    ['id' => $id(), 'type' => 'cta', 'data' => ['title' => 'Bereit?', 'text' => 'Wir freuen uns auf Ihre Nachricht.', 'button_label' => 'Kontakt aufnehmen', 'button_url' => '/kontakt', 'style' => 'dark']],
                ],
            ],
            'aktion' => [
                'title' => 'Aktion',
                'show_in_menu' => false,
                'blocks' => [
                    ['id' => $id(), 'type' => 'hero', 'data' => ['variant' => 'video', 'eyebrow' => 'Nur bis Sonntag', 'title' => '20 % auf alles', 'text' => 'Beschreiben Sie Ihre Aktion in einem Satz: was, für wen, bis wann.', 'button_label' => 'Zum Angebot', 'button_url' => '/shop', 'overlay' => 'brand', 'opacity' => 60, 'height' => 'tall', 'align' => 'center', 'image' => null, 'video' => null]],
                    ['id' => $id(), 'type' => 'features', 'data' => ['eyebrow' => '', 'title' => 'So einfach geht es', 'intro' => '', 'style' => 'steps', 'items' => [
                        ['title' => 'Aussuchen', 'text' => 'Wählen Sie Ihr Lieblingsprodukt.'], ['title' => 'Code eingeben', 'text' => 'Nennen Sie bei der Bestellung das Stichwort.'], ['title' => 'Sparen', 'text' => 'Der Rabatt wird sofort abgezogen.'],
                    ]]],
                    ['id' => $id(), 'type' => 'newsletter', 'data' => ['eyebrow' => 'Keine Aktion mehr verpassen', 'title' => 'Erfahren Sie es zuerst', 'intro' => 'Abonnieren Sie unseren Newsletter, dann sind Sie bei der nächsten Aktion als Erste dabei.', 'button_label' => 'Abonnieren', 'ask_name' => false, 'style' => 'dark']],
                ],
            ],
            'leistungen' => [
                'title' => 'Leistungen',
                'blocks' => [
                    ['id' => $id(), 'type' => 'hero', 'data' => ['variant' => 'tech', 'eyebrow' => 'Leistungen', 'title' => 'Was wir für Sie tun', 'text' => 'Ein Satz, der Ihr Angebot auf den Punkt bringt.', 'button_label' => 'Angebot anfragen', 'button_url' => '/kontakt', 'image' => null]],
                    ['id' => $id(), 'type' => 'features', 'data' => ['eyebrow' => 'Ablauf', 'title' => 'So arbeiten wir', 'intro' => '', 'style' => 'steps', 'items' => [
                        ['title' => 'Kennenlernen', 'text' => 'Wir besprechen Ihre Wünsche.'], ['title' => 'Angebot', 'text' => 'Sie erhalten ein klares Angebot.'], ['title' => 'Umsetzung', 'text' => 'Wir legen los, Sie lehnen sich zurück.'],
                    ]]],
                    ['id' => $id(), 'type' => 'pricing', 'data' => ['eyebrow' => 'Preise', 'title' => 'Pakete', 'intro' => '', 'note' => 'Alle Preise inklusive Mehrwertsteuer.', 'plans' => [
                        ['name' => 'Basis', 'price' => '99 €', 'period' => 'einmalig', 'text' => 'Für den Einstieg', 'features' => ['Leistung 1', 'Leistung 2'], 'button_label' => 'Anfragen', 'button_url' => '/kontakt', 'highlight' => false],
                        ['name' => 'Komfort', 'price' => '199 €', 'period' => 'einmalig', 'text' => 'Am beliebtesten', 'features' => ['Alles aus Basis', 'Leistung 3', 'Leistung 4'], 'button_label' => 'Anfragen', 'button_url' => '/kontakt', 'highlight' => true],
                        ['name' => 'Premium', 'price' => '349 €', 'period' => 'einmalig', 'text' => 'Rundum sorglos', 'features' => ['Alles aus Komfort', 'Persönliche Betreuung'], 'button_label' => 'Anfragen', 'button_url' => '/kontakt', 'highlight' => false],
                    ]]],
                    ['id' => $id(), 'type' => 'faq', 'data' => ['eyebrow' => '', 'title' => 'Häufige Fragen', 'intro' => '', 'items' => [['q' => 'Wie schnell geht es los?', 'a' => 'In der Regel innerhalb einer Woche.']]]],
                ],
            ],
            'newsletter' => [
                'title' => 'Newsletter',
                'blocks' => [
                    ['id' => $id(), 'type' => 'features', 'data' => ['eyebrow' => 'Newsletter', 'title' => 'Darum lohnt sich unser Newsletter', 'intro' => 'Etwa einmal im Monat, kein Spam, jederzeit abbestellbar.', 'style' => 'grid', 'items' => [
                        ['icon' => 'bolt', 'title' => 'Aktionen zuerst', 'text' => 'Abonnenten erfahren von Angeboten vor allen anderen.'],
                        ['icon' => 'sparkles', 'title' => 'Neuigkeiten', 'text' => 'Neue Produkte und Termine direkt in Ihr Postfach.'],
                        ['icon' => 'heart', 'title' => 'Tipps', 'text' => 'Praktische Ideen rund um unser Angebot.'],
                    ]]],
                    ['id' => $id(), 'type' => 'newsletter', 'data' => ['eyebrow' => '', 'title' => 'Jetzt kostenlos abonnieren', 'intro' => 'Nach der Anmeldung schicken wir Ihnen eine E-Mail zur Bestätigung.', 'button_label' => 'Abonnieren', 'ask_name' => true, 'style' => 'dark']],
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
            'barrierefreiheit' => [
                'title' => 'Erklärung zur Barrierefreiheit',
                'purpose' => 'accessibility',
                'show_in_menu' => false,
                'blocks' => [$text(self::accessibilityText($name, $address, $contact, $missing))],
            ],
            default => ['title' => 'Neue Seite', 'blocks' => [$text('<p></p>')]],
        };
    }

    /** Erklärung nach BFSG § 14 und Anlage 3, vorbefüllt mit dem, was Nova CMS von Haus aus leistet. */
    public static function accessibilityText(string $name, string $address, string $contact, string $missing): string
    {
        return '<h2>Unser Ziel</h2><p>'.$name.' möchte, dass alle Menschen diese Website und unseren Online-Shop nutzen können, auch mit Seh-, Hör- oder motorischen Einschränkungen. Grundlage sind das Barrierefreiheitsstärkungsgesetz (BFSG) und die Web-Richtlinien WCAG 2.1 Stufe AA (EN 301 549).</p>'
            .'<h2>Stand der Barrierefreiheit</h2><p>Diese Website ist <strong>weitgehend barrierefrei</strong>. Stand dieser Erklärung: '.now()->format('d.m.Y').'.</p>'
            .'<h2>So unterstützt Sie diese Website</h2><ul>'
            .'<li>Alle Seiten lassen sich vollständig mit der Tastatur bedienen. Ein Link „Zum Inhalt springen“ führt direkt zum Hauptinhalt, der Fokus ist immer deutlich sichtbar.</li>'
            .'<li>Überschriften, Menüs und Bereiche sind so ausgezeichnet, dass Screenreader sie vorlesen und Sie gezielt springen können.</li>'
            .'<li>Bilder haben Textbeschreibungen, Formularfelder sind beschriftet und Fehlermeldungen werden vorgelesen.</li>'
            .'<li>Texte lassen sich auf 200 % vergrößern, die Darstellung passt sich Smartphone, Tablet und Computer an.</li>'
            .'<li>Bewegungen und Hintergrundvideos werden angehalten, wenn Sie in Ihrem Gerät „Bewegung reduzieren“ eingestellt haben.</li>'
            .'<li>Externe Videos werden erst nach Ihrem Klick geladen.</li>'
            .'</ul>'
            .'<h2>Bekannte Einschränkungen</h2><p>[Bitte ergänzen, falls zutreffend, z. B.: Ältere PDF-Dokumente sind noch nicht barrierefrei. Videos haben noch keine Untertitel. Wir arbeiten daran.]</p>'
            .'<h2>Barriere melden</h2><p>Ist Ihnen eine Barriere aufgefallen, oder benötigen Sie Inhalte in einer anderen Form? Schreiben Sie uns, wir antworten so schnell wie möglich:<br>'.$name.'<br>'.($address ?: $missing).'<br>'.($contact ?: $missing).'</p>'
            .'<h2>Zuständige Marktüberwachungsbehörde</h2><p>Wenn Sie mit unserer Antwort nicht zufrieden sind, können Sie sich an die Marktüberwachungsstelle der Länder für die Barrierefreiheit von Produkten und Dienstleistungen (MLBF) in Magdeburg wenden.</p>'
            .'<p><em>Hinweis: Diese Vorlage ersetzt keine Rechtsberatung. Kleinstunternehmen mit weniger als 10 Beschäftigten und höchstens 2 Mio. Euro Jahresumsatz sind bei Dienstleistungen von den BFSG-Pflichten ausgenommen. Prüfen Sie die Angaben mit dem Barrierefreiheits-Check in der Verwaltung.</em></p>';
    }
}
