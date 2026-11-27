<?php

namespace Tests\Feature;

use App\Ai\AiDriver;
use App\Ai\AiManager;
use App\Licensing\Features;
use App\Licensing\License;
use App\Licensing\LicenseKey;
use App\Licensing\LicenseServer;
use App\Licensing\ModulePricing;
use App\Licensing\Signer;
use App\Mail\BookingMail;
use App\Mail\MemberMail;
use App\Mail\VoucherMail;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\IssuedLicense;
use App\Models\MemberFile;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Shop\Cart;
use App\Support\Backup\Backups;
use App\Support\Booking\BookingCalendar;
use App\Support\Booking\Bookings;
use App\Support\Starter\NovaSalesSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Version 0.7: Terminbuchung, Gutscheine, Mehrsprachigkeit, Mitgliederbereich, Warenwirtschaft */
class MoreModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Setting::putMany(['site_name' => 'Radwerk', 'contact_email' => 'info@example.com']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    // ---------- Terminbuchung ----------

    public function test_termin_buchen_bestaetigen_und_absagen(): void
    {
        Carbon::setTestNow('2026-12-07 08:00:00'); // Montag
        Setting::put('booking_lead_hours', '2');
        $service = Service::create(['name' => 'Inspektion', 'duration' => 60, 'buffer' => 30, 'is_active' => true]);

        $this->get('/termine/buchen')->assertOk()->assertSee('Inspektion');
        $this->get('/termine/buchen?leistung='.$service->id)->assertOk()->assertSee('Mo');
        $page = $this->get('/termine/buchen?leistung='.$service->id.'&tag=2026-12-07')->assertOk();
        // Vorlauf 2 Stunden: erster Termin 10:00, letzter 16:00 (bis 17 Uhr)
        $page->assertDontSee('value="2026-12-07 09:30"', false)->assertSee('value="2026-12-07 10:00"', false)->assertSee('value="2026-12-07 16:00"', false)
            ->assertDontSee('value="2026-12-07 16:30"', false);

        $data = ['service' => $service->id, 'start' => '2026-12-07 10:00', 'name' => 'Erika Muster', 'email' => 'erika@example.com', 'consent' => '1'];
        $this->post('/termine/buchen', ['consent' => null] + $data)->assertSessionHasErrors('consent');
        $res = $this->post('/termine/buchen', $data);
        $booking = Booking::firstOrFail();
        $res->assertRedirect(route('booking.status', $booking->token));
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame('2026-12-07 11:00', $booking->ends_at->format('Y-m-d H:i'));
        Mail::assertSent(BookingMail::class, fn ($m) => $m->hasTo('erika@example.com'));
        Mail::assertSent(BookingMail::class, fn ($m) => $m->hasTo('info@example.com'));

        // Belegt, inklusive Pause danach: 10:00 bis 11:30
        $this->post('/termine/buchen', ['start' => '2026-12-07 11:00'] + $data)->assertSessionHasErrors('start');
        $this->get('/termine/buchen?leistung='.$service->id.'&tag=2026-12-07')->assertDontSee('value="2026-12-07 10:30"', false)
            ->assertDontSee('value="2026-12-07 11:00"', false)->assertSee('value="2026-12-07 11:30"', false);

        $this->get(route('booking.status', $booking->token))->assertOk()->assertSee('Inspektion')->assertSee('Bestätigt');
        $this->get(route('booking.ics', $booking->token))->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertSee('DTSTART:20261207T')->assertSee('SUMMARY:Inspektion');

        $this->post(route('booking.cancel.do', $booking->token))->assertRedirect();
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->get('/termine/buchen?leistung='.$service->id.'&tag=2026-12-07')->assertSee('value="2026-12-07 10:00"', false);
    }

    public function test_termine_mit_bestaetigung_und_erinnerung(): void
    {
        Carbon::setTestNow('2026-12-07 08:00:00');
        Setting::putMany(['booking_confirm' => 'manual', 'booking_lead_hours' => '0']);
        $service = Service::create(['name' => 'Beratung', 'duration' => 30, 'is_active' => true]);
        $this->post('/termine/buchen', ['service' => $service->id, 'start' => '2026-12-08 09:00', 'name' => 'Max', 'email' => 'max@example.com', 'consent' => '1']);
        $booking = Booking::firstOrFail();
        $this->assertSame('pending', $booking->status);

        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/termine')->assertOk()->assertSee('1 Anfrage wartet')->assertSee('Max');
        $this->actingAs($admin)->post(route('admin.bookings.confirm', $booking))->assertRedirect();
        $this->assertSame('confirmed', $booking->fresh()->status);

        Carbon::setTestNow('2026-12-07 10:00:00');
        $this->assertSame(1, app(Bookings::class)->remind());
        $this->assertSame(0, app(Bookings::class)->remind());
        $this->assertNotNull($booking->fresh()->reminded_at);

        // Geschlossene Tage und Öffnungszeiten
        $this->actingAs($admin)->put('/admin/termine/einstellungen', [
            'hours' => ['2' => [['from' => '14:00', 'to' => '16:00']]], 'closed' => "09.12.2026\nquatsch",
            'booking_confirm' => 'auto', 'booking_lead_hours' => 0, 'booking_days_ahead' => 30, 'booking_step' => 60, 'booking_capacity' => 1,
        ])->assertRedirect();
        $this->assertSame(['2026-12-09'], json_decode(Setting::get('booking_closed'), true));
        $this->assertSame(['14:00', '15:00'], app(BookingCalendar::class)->slots($service, Carbon::parse('2026-12-08'))->map->format('H:i')->all());
        $this->assertTrue(app(BookingCalendar::class)->slots($service, Carbon::parse('2026-12-07'))->isEmpty());
    }

    // ---------- Gutscheine ----------

    private function shop(): Product
    {
        Setting::putMany(['shop_enabled' => '1', 'shop_iban' => 'DE89 3704 0044 0532 0130 00', 'shop_shipping_cents' => '490', 'shop_free_from_cents' => '']);

        return Product::create(['name' => 'Fahrradlampe', 'slug' => 'lampe', 'price_cents' => 5000, 'tax_rate' => 19, 'is_active' => true]);
    }

    private function customer(): array
    {
        return ['email' => 'kunde@example.com', 'name' => 'Erika Mustermann', 'street' => 'Hauptstraße 1', 'zip' => '50667',
            'city' => 'Köln', 'country' => 'DE', 'payment_method' => 'prepayment', 'accept_terms' => '1'];
    }

    public function test_rabattcode_im_warenkorb_und_in_der_bestellung(): void
    {
        $p = $this->shop();
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/gutscheine', ['code' => 'herbst 10', 'kind' => 'percent', 'value' => '10', 'min_order' => '30', 'max_uses' => 1])
            ->assertRedirect();
        $this->assertSame('HERBST10', Coupon::firstOrFail()->code);
        $this->actingAs($admin)->get('/admin/gutscheine')->assertOk()->assertSee('HERBST10')->assertSee('10 %')->assertSee('ab 30,00 €');
        $this->actingAs($admin)->post('/admin/gutscheine', ['code' => 'HERBST10', 'kind' => 'percent', 'value' => '5'])->assertSessionHasErrors('code');
        $this->actingAs($admin)->post('/admin/gutscheine', ['kind' => 'percent', 'value' => '150'])->assertSessionHasErrors('value');
        auth()->logout();

        $this->post('/warenkorb/gutschein', ['code' => 'GIBTSNICHT'])->assertSessionHasErrors('code');
        $this->post('/warenkorb', ['product' => $p->id, 'quantity' => 2]);
        $this->post('/warenkorb/gutschein', ['code' => 'herbst10'])->assertSessionHas('status');
        $this->get('/warenkorb')->assertOk()->assertSee('HERBST10')->assertSee('−10,00 €')->assertSee('94,90 €');

        $this->post('/kasse', $this->customer());
        $order = Order::firstOrFail();
        $this->assertSame(1000, $order->discount_cents);
        $this->assertSame('HERBST10', $order->coupon_code);
        $this->assertSame(10000 - 1000 + 490, $order->total_cents);
        // MwSt aus 90 € Ware + 4,90 € Versand
        $this->assertSame(1515, $order->taxes[0]['cents']);
        $this->assertSame(1, Coupon::first()->used);
        $this->get(route('shop.order', $order->token))->assertSee('HERBST10');

        // Nur einmal einlösbar
        $this->post('/warenkorb', ['product' => $p->id, 'quantity' => 1]);
        $this->post('/warenkorb/gutschein', ['code' => 'HERBST10'])->assertSessionHasErrors(['code' => 'Dieser Code wurde bereits eingelöst.']);
    }

    public function test_geschenkgutschein_verkaufen_und_einloesen(): void
    {
        $lamp = $this->shop();
        $voucher = Product::create(['name' => 'Geschenkgutschein 25 €', 'slug' => 'gutschein-25', 'price_cents' => 2500, 'tax_rate' => 19, 'is_active' => true, 'is_voucher' => true]);
        $this->post('/warenkorb', ['product' => $voucher->id, 'quantity' => 2]);
        $this->post('/kasse', $this->customer());
        $order = Order::firstOrFail();
        $this->assertSame(0, Coupon::count());

        $order->markPaid();
        $codes = Coupon::where('order_id', $order->id)->get();
        $this->assertCount(2, $codes);
        $this->assertTrue($codes[0]->is_gift);
        $this->assertSame(2500, $codes[0]->value);
        Mail::assertSent(VoucherMail::class, fn ($m) => $m->hasTo('kunde@example.com') && str_contains($m->render(), $codes[0]->code));

        // Einlösen: 25 € auf eine 50-€-Lampe, Restguthaben 0
        $this->post('/warenkorb', ['product' => $lamp->id, 'quantity' => 1]);
        $this->post('/warenkorb/gutschein', ['code' => $codes[0]->code]);
        $this->post('/kasse', $this->customer());
        $second = Order::latest('id')->first();
        $this->assertSame(5000 - 2500 + 490, $second->total_cents);
        $this->assertSame(0, $codes[0]->fresh()->value);

        // Zweiter Gutschein auf kleinen Einkauf: Restwert bleibt
        $small = Product::create(['name' => 'Klingel', 'slug' => 'klingel', 'price_cents' => 900, 'tax_rate' => 19, 'is_active' => true]);
        $this->post('/warenkorb', ['product' => $small->id, 'quantity' => 1]);
        $this->post('/warenkorb/gutschein', ['code' => $codes[1]->code]);
        $this->post('/kasse', $this->customer());
        $this->assertSame(1600, $codes[1]->fresh()->value);
        $this->actingAs($this->admin())->get('/admin/gutscheine?art=geschenk')->assertOk()->assertSee($codes[1]->code)->assertSee('16,00 € Guthaben');
    }

    public function test_versandkostenfrei_code(): void
    {
        $p = $this->shop();
        Coupon::create(['code' => 'FREI', 'kind' => 'shipping', 'value' => 0, 'is_active' => true]);
        $this->post('/warenkorb', ['product' => $p->id, 'quantity' => 1]);
        $this->post('/warenkorb/gutschein', ['code' => 'frei']);
        $this->post('/kasse', $this->customer());
        $this->assertSame(5000, Order::first()->total_cents);
        $this->assertSame(0, Order::first()->shipping_cents);
    }

    // ---------- Mehrsprachigkeit ----------

    private function fakeTranslator(): void
    {
        Setting::putMany(['ai_provider' => 'anthropic', 'ai_model' => 'claude-opus-5-5', 'ai_key' => AiManager::encryptKey('k')]);
        app(AiManager::class)->fake(new class implements AiDriver
        {
            public function complete(string $system, string $prompt, ?array $schema = null, int $maxTokens = 4000): string
            {
                $in = json_decode($prompt, true)['texte'];

                return json_encode(['texte' => array_map(fn ($t) => strtr($t, ['Leistungen' => 'Services', 'Wir reparieren Fahrräder' => 'We repair bikes', 'Startseite' => 'Home', 'Willkommen' => 'Welcome']), $in)]);
            }
        });
    }

    public function test_seite_uebersetzen_und_sprachauswahl(): void
    {
        $this->fakeTranslator();
        $home = Page::create(['title' => 'Startseite', 'slug' => 'startseite', 'status' => 'published', 'is_home' => true, 'show_in_menu' => false,
            'blocks' => [['id' => 'h', 'type' => 'hero', 'data' => ['title' => 'Willkommen', 'text' => 'Wir reparieren Fahrräder']]]]);
        $page = Page::create(['title' => 'Leistungen', 'slug' => 'leistungen', 'status' => 'published', 'show_in_menu' => true,
            'blocks' => [['id' => 't', 'type' => 'text', 'data' => ['html' => '<p>Wir reparieren Fahrräder</p>']]]]);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/sprachen')->assertOk()->assertSee('Englisch');
        $this->actingAs($admin)->put('/admin/sprachen', ['languages' => ['en', 'fr', 'xx']])->assertRedirect();
        $this->assertSame(['en', 'fr'], json_decode(Setting::get('languages'), true));

        $this->actingAs($admin)->get('/admin/seiten/'.$page->id)->assertOk()->assertSee('Übersetzungen')->assertSee('data-translate="en"', false);
        $this->actingAs($admin)->post('/admin/seiten/'.$page->id.'/uebersetzung', ['locale' => 'en', 'ai' => 1])->assertRedirect();
        $en = Page::where('locale', 'en')->firstOrFail();
        $this->assertSame('Services', $en->title);
        $this->assertSame('services', $en->slug);
        $this->assertSame($page->id, $en->translation_of);
        $this->assertSame('draft', $en->status);
        $this->assertStringContainsString('We repair bikes', $en->blocks[0]['data']['html']);
        $this->assertSame(1, MenuItem::count());
        // Zweiter Aufruf öffnet die vorhandene Übersetzung
        $this->actingAs($admin)->post('/admin/seiten/'.$page->id.'/uebersetzung', ['locale' => 'en'])->assertRedirect(route('admin.pages.edit', $en));
        $this->actingAs($admin)->post('/admin/seiten/'.$home->id.'/uebersetzung', ['locale' => 'en', 'ai' => 1]);
        Page::where('locale', 'en')->update(['status' => 'published', 'published_at' => now()]);
        auth()->logout();

        $this->get('/en/services')->assertOk()->assertSee('<html lang="en">', false)->assertSee('We repair bikes')
            ->assertSee('hreflang="de" href="'.url('/leistungen').'"', false)->assertSee('hreflang="en" href="'.url('/en/services').'"', false)
            ->assertSee('Skip to content')->assertSee('href="'.url('/en/services').'"', false);
        $this->get('/en')->assertOk()->assertSee('Welcome');
        $de = $this->get('/leistungen')->assertOk()->assertSee('<html lang="de">', false)->assertSee('data-language-switcher', false)
            ->assertSee('href="'.url('/en/services').'"', false)->assertSee('href="'.url('/fr').'"', false);
        $this->get('/services')->assertRedirect('/en/services');
        $this->get('/fr')->assertRedirect('/');
        $this->get('/es/services')->assertNotFound();
        $this->get('/sitemap.xml')->assertSee(url('/en/services'));
        $this->actingAs($admin)->get('/admin/seiten')->assertOk()->assertSee('data-page-translations', false)->assertDontSee('>Services</a>', false);

        // Slugs mit Sprachkürzeln sind reserviert
        $this->assertSame('en-seite', Page::uniqueSlug('en'));
    }

    public function test_ohne_modul_keine_sprachen(): void
    {
        Setting::put('languages', json_encode(['en']));
        $this->customerLicense([]);
        Page::create(['title' => 'AG', 'slug' => 'ag', 'status' => 'published', 'blocks' => []]);
        $this->get('/ag')->assertOk();
        $this->get('/en')->assertNotFound();
        $this->get('/ag')->assertDontSee('data-language-switcher', false);
    }

    // ---------- Mitgliederbereich ----------

    public function test_mitglieder_registrieren_freischalten_und_geschuetzte_seite(): void
    {
        $secret = Page::create(['title' => 'Preisliste Händler', 'slug' => 'haendler', 'status' => 'published', 'show_in_menu' => true, 'visibility' => 'members',
            'blocks' => [['id' => 't', 'type' => 'text', 'data' => ['html' => '<p>Geheimer Rabatt 30 Prozent</p>']]]]);

        $this->get('/haendler')->assertForbidden()->assertSee('nur für Mitglieder')->assertDontSee('Geheimer Rabatt')->assertSee('name="password"', false);
        Page::create(['title' => 'Kontakt', 'slug' => 'kontakt', 'status' => 'published', 'blocks' => []]);
        $this->get('/kontakt')->assertOk()->assertDontSee('Preisliste Händler');
        $this->get('/sitemap.xml')->assertDontSee('haendler');
        $this->get('/suche?q=Rabatt')->assertDontSee('Preisliste Händler');

        $this->post('/mitglieder/registrieren', ['name' => 'Hans Händler', 'email' => 'hans@example.com', 'password' => 'geheim12345', 'password_confirmation' => 'geheim12345'])
            ->assertSessionHasErrors('consent');
        $this->post('/mitglieder/registrieren', ['name' => 'Hans Händler', 'email' => 'hans@example.com', 'password' => 'geheim12345', 'password_confirmation' => 'geheim12345', 'consent' => '1'])
            ->assertRedirect(route('members.login'));
        $hans = User::where('email', 'hans@example.com')->firstOrFail();
        $this->assertSame('member_pending', $hans->role);
        Mail::assertSent(MemberMail::class, fn ($m) => $m->hasTo('info@example.com') && $m->kind === 'owner');

        $this->post('/mitglieder/anmelden', ['email' => 'hans@example.com', 'password' => 'geheim12345'])->assertSessionHasErrors(['email' => 'Ihr Zugang wartet noch auf die Freigabe. Sie bekommen eine E-Mail, sobald es losgehen kann.']);
        $this->assertGuest();

        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/mitglieder')->assertOk()->assertSee('1 Registrierung wartet')->assertSee('hans@example.com');
        $this->actingAs($admin)->put('/admin/mitglieder/'.$hans->id, ['action' => 'approve'])->assertRedirect();
        $this->assertSame('member', $hans->fresh()->role);
        Mail::assertSent(MemberMail::class, fn ($m) => $m->hasTo('hans@example.com') && $m->kind === 'approved');
        Storage::fake('local');
        $this->actingAs($admin)->post('/admin/mitglieder/dateien', ['title' => 'Katalog', 'file' => UploadedFile::fake()->create('katalog.pdf', 120, 'application/pdf')])->assertRedirect();
        $file = MemberFile::firstOrFail();
        $this->actingAs($admin)->post('/admin/mitglieder/dateien', ['file' => UploadedFile::fake()->create('virus.php', 1)])->assertSessionHasErrors('file');
        auth()->logout();
        $this->get('/mitglieder/downloads/'.$file->id)->assertRedirect(route('members.login'));

        $this->post('/mitglieder/anmelden', ['email' => 'hans@example.com', 'password' => 'geheim12345'])->assertRedirect(url('/haendler'));
        $this->get('/haendler')->assertOk()->assertSee('Geheimer Rabatt')->assertSee('<meta name="robots" content="noindex">', false);
        $this->get('/kontakt')->assertSee('Preisliste Händler');
        $this->get('/mitglieder')->assertOk()->assertSee('Willkommen, Hans Händler')->assertSee('Katalog')->assertSee('Preisliste Händler');
        $this->get('/mitglieder/downloads/'.$file->id)->assertOk()->assertDownload('katalog.pdf');
        // Kein Zugang zur Verwaltung
        $this->get('/admin')->assertForbidden();

        $this->actingAs($admin)->put('/admin/mitglieder/'.$hans->id, ['action' => 'block']);
        $this->actingAs($hans->fresh())->get('/haendler')->assertForbidden();
    }

    public function test_mitglied_einladen_und_passwort_festlegen(): void
    {
        $this->actingAs($this->admin())->post('/admin/mitglieder/einladen', ['name' => 'Eva', 'email' => 'eva@example.com'])->assertRedirect();
        $url = null;
        Mail::assertSent(MemberMail::class, function ($m) use (&$url) {
            $url = $m->url;

            return $m->hasTo('eva@example.com') && $m->kind === 'invite' && str_contains($m->render(), 'Passwort festlegen');
        });
        auth()->logout();
        $this->get($url)->assertOk()->assertSee('Neues Passwort festlegen');
        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        $token = basename(parse_url($url, PHP_URL_PATH));
        $this->post('/passwort-neu', ['token' => 'falsch', 'email' => 'eva@example.com', 'password' => 'evaspasswort1', 'password_confirmation' => 'evaspasswort1'])->assertSessionHasErrors('email');
        $this->post('/passwort-neu', ['token' => $token, 'email' => $q['email'], 'password' => 'evaspasswort1', 'password_confirmation' => 'evaspasswort1'])->assertRedirect(route('members.login'));
        $this->post('/mitglieder/anmelden', ['email' => 'eva@example.com', 'password' => 'evaspasswort1'])->assertRedirect(route('members.home'));
        $this->post('/admin/login', ['email' => 'eva@example.com', 'password' => 'evaspasswort1']);

        // Mitglieder können sich nicht in der Verwaltung anmelden
        auth()->logout();
        $this->post('/admin/login', ['email' => 'eva@example.com', 'password' => 'evaspasswort1'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    // ---------- Warenwirtschaft ----------

    public function test_warenwirtschaft_csv_und_schnittstelle(): void
    {
        $this->shop();
        $lamp = Product::where('slug', 'lampe')->first();
        $lamp->update(['sku' => 'L-100', 'stock' => 5]);
        Product::create(['name' => '=HYPERLINK("x")', 'slug' => 'boese', 'sku' => 'B-1', 'price_cents' => 100, 'tax_rate' => 19, 'is_active' => true]);
        $admin = $this->admin();

        $csv = $this->actingAs($admin)->get('/admin/warenwirtschaft/produkte.csv')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBFartikelnummer;name;preis", $csv);
        $this->assertStringContainsString('L-100;Fahrradlampe;50,00;;19;5;ja;', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);

        $file = UploadedFile::fake()->createWithContent('neu.csv', "artikelnummer;preis;bestand;aktiv\nl-100;45,90;12;ja\nGIBTS-NICHT;1;1;ja\nB-1;abc;;\n");
        $this->actingAs($admin)->post('/admin/warenwirtschaft/import', ['file' => $file])->assertRedirect()->assertSessionHas('status', '1 Produkt aktualisiert.');
        $this->assertSame(4590, $lamp->fresh()->price_cents);
        $this->assertSame(12, $lamp->fresh()->stock);
        $this->assertCount(2, session('erp_skipped'));

        // Schnittstelle: ohne Schlüssel gesperrt
        auth()->logout();
        $this->getJson('/api/nova/ww/produkte')->assertUnauthorized();
        $this->actingAs($admin)->post('/admin/warenwirtschaft/schluessel')->assertRedirect();
        $token = session('erp_token');
        $this->assertStringStartsWith('nww_', $token);
        $this->assertNotSame($token, Setting::get('erp_token_hash'));
        auth()->logout();
        $this->getJson('/api/nova/ww/produkte', ['Authorization' => 'Bearer falsch'])->assertUnauthorized();
        $h = ['Authorization' => 'Bearer '.$token];
        $this->getJson('/api/nova/ww/produkte', $h)->assertOk()->assertJsonPath('data.0.sku', 'L-100');
        $this->patchJson('/api/nova/ww/produkte/L-100', ['stock' => 3, 'price_cents' => 4990], $h)->assertOk()->assertJsonPath('data.stock', 3);
        $this->assertSame(4990, $lamp->fresh()->price_cents);
        $this->patchJson('/api/nova/ww/produkte/XYZ', ['stock' => 3], $h)->assertNotFound();

        $this->post('/warenkorb', ['product' => $lamp->id, 'quantity' => 1]);
        $this->post('/kasse', $this->customer());
        $order = Order::firstOrFail();
        $this->getJson('/api/nova/ww/bestellungen?seit='.urlencode(now()->subDay()->toIso8601String()), $h)->assertOk()
            ->assertJsonPath('data.0.number', $order->number)->assertJsonPath('data.0.items.0.sku', 'L-100');
        $this->patchJson('/api/nova/ww/bestellungen/'.$order->number, ['status' => 'shipped', 'tracking_number' => 'DHL123', 'payment_status' => 'paid'], $h)
            ->assertOk()->assertJsonPath('data.status', 'shipped')->assertJsonPath('data.payment_status', 'paid');
        $this->assertNotNull($order->fresh()->shipped_at);
        $this->patchJson('/api/nova/ww/bestellungen/'.$order->number, ['status' => 'verloren'], $h)->assertUnprocessable();

        $orders = $this->actingAs($admin)->get('/admin/warenwirtschaft/bestellungen.csv')->assertOk()->getContent();
        $this->assertStringContainsString($order->number.';', $orders);
        $this->assertStringContainsString('DHL123', $orders);
    }

    // ---------- Datensicherung ----------

    public function test_datensicherung_erstellen_und_zurueckspielen(): void
    {
        $page = Page::create(['title' => 'Über uns', 'slug' => 'ueber-uns', 'status' => 'published', 'blocks' => []]);
        Setting::put('license_key', 'BLEIBT');
        @mkdir(public_path('uploads/test'), 0777, true);
        file_put_contents(public_path('uploads/test/bild.txt'), 'Original');
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/datensicherung')->assertOk()->assertSee('Noch keine Sicherung');
        $this->actingAs($admin)->post('/admin/datensicherung')->assertRedirect();
        $backup = Backups::list()->first();
        $this->assertNotNull($backup);
        $zip = new \ZipArchive;
        $zip->open(Backups::path($backup['name']));
        $this->assertNotFalse($zip->locateName('database/pages.jsonl'));
        $this->assertNotFalse($zip->locateName('files/uploads/test/bild.txt'));
        $this->assertFalse($zip->locateName('database/sessions.jsonl'));
        $zip->close();
        $this->actingAs($admin)->get('/admin/datensicherung/'.$backup['name'])->assertOk()->assertDownload($backup['name']);
        $this->assertContains($this->actingAs($admin)->get('/admin/datensicherung/..%2F.env')->status(), [403, 404]);
        $this->actingAs($admin)->get('/admin/datensicherung/nova-sicherung-gibts-nicht.zip')->assertNotFound();

        // Änderungen nach der Sicherung
        $page->update(['title' => 'Kaputt']);
        Page::create(['title' => 'Neu', 'slug' => 'neu', 'status' => 'draft', 'blocks' => []]);
        Setting::put('license_key', 'NEUER-SCHLUESSEL');
        file_put_contents(public_path('uploads/test/bild.txt'), 'Überschrieben');

        $this->actingAs($admin)->post('/admin/datensicherung/zurueckspielen', ['name' => $backup['name']])->assertSessionHasErrors('confirm');
        $this->actingAs($admin)->post('/admin/datensicherung/zurueckspielen', ['name' => $backup['name'], 'confirm' => '1'])->assertRedirect()->assertSessionHas('status');
        $this->assertSame('Über uns', $page->fresh()->title);
        $this->assertFalse(Page::where('slug', 'neu')->exists());
        $this->assertSame('Original', file_get_contents(public_path('uploads/test/bild.txt')));
        $this->assertSame('NEUER-SCHLUESSEL', Setting::get('license_key')); // Lizenz dieser Installation bleibt
        $this->assertTrue(Backups::list()->contains('kind', 'Vor Wiederherstellung'));

        // Keine fremden Dateien
        $bad = UploadedFile::fake()->createWithContent('x.zip', 'kein zip');
        $this->actingAs($admin)->post('/admin/datensicherung/zurueckspielen', ['file' => $bad, 'confirm' => '1'])->assertSessionHasErrors('backup');

        // Automatisch, ohne Cronjob
        Setting::putMany(['backup_auto' => 'daily', 'backup_keep' => '1']);
        $this->assertNotNull(app(Backups::class)->runDue());
        $this->assertNull(app(Backups::class)->runDue());
        $this->travel(25)->hours();
        $this->assertNotNull(app(Backups::class)->runDue());
        $this->assertSame(1, Backups::list()->where('kind', 'Automatisch')->count());

        File::deleteDirectory(public_path('uploads/test'));
        File::deleteDirectory(storage_path('app/backups'));
    }

    // ---------- Google-Bewertungen ----------

    public function test_google_bewertungen(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response([
            'displayName' => ['text' => 'Radwerk Erfurt'], 'rating' => 4.7, 'userRatingCount' => 128, 'googleMapsUri' => 'https://maps.google.com/?cid=1',
            'reviews' => [
                ['rating' => 5, 'text' => ['text' => 'Super schnelle Reparatur!'], 'authorAttribution' => ['displayName' => 'Anna B.', 'uri' => 'https://www.google.com/maps/contrib/1'], 'relativePublishTimeDescription' => 'vor 2 Wochen'],
                ['rating' => 2, 'text' => ['text' => 'Zu teuer.'], 'authorAttribution' => ['displayName' => 'Max'], 'relativePublishTimeDescription' => 'vor 1 Monat'],
            ],
        ])]);
        $admin = $this->admin();
        $this->actingAs($admin)->put('/admin/google-bewertungen', ['place_id' => 'https://search.google.com/local/writereview?placeid=ChIJ1234567890abcdefghij', 'api_key' => 'AIza-test', 'reviews_min_rating' => 4])
            ->assertRedirect()->assertSessionHas('status', 'Verbunden: Radwerk Erfurt, 4,7 Sterne aus 128 Bewertungen.');
        $this->assertSame('ChIJ1234567890abcdefghij', Setting::get('google_place_id'));
        $this->assertNotSame('AIza-test', Setting::get('google_places_key'));
        Http::assertSent(fn ($r) => $r->hasHeader('X-Goog-Api-Key', 'AIza-test') && str_contains($r->url(), 'places/ChIJ1234567890abcdefghij'));
        $this->actingAs($admin)->get('/admin/google-bewertungen')->assertOk()->assertSee('writereview?placeid=ChIJ1234567890abcdefghij')->assertSee('<svg', false);

        Page::create(['title' => 'Start', 'slug' => 'start', 'status' => 'published', 'is_home' => true,
            'blocks' => [['id' => 'r', 'type' => 'reviews', 'data' => ['title' => 'Das sagen Kunden', 'limit' => 3, 'show_button' => true]]]]);
        auth()->logout();
        $this->get('/')->assertOk()->assertSee('4,7')->assertSee('128 Bewertungen bei Google')->assertSee('Super schnelle Reparatur!')
            ->assertDontSee('Zu teuer.')->assertSee('Bewertung schreiben');
    }

    // ---------- Kundenkonto ----------

    public function test_kundenkonto_registrieren_adressen_und_bestellungen(): void
    {
        $p = $this->shop();
        $this->get('/kasse'); // leerer Warenkorb
        $this->post('/warenkorb', ['product' => $p->id, 'quantity' => 1]);
        $this->get('/kasse')->assertOk()->assertSee('Schon Kunde?')->assertSee('data-billing-different', false);

        $this->post('/konto/registrieren', ['name' => 'Erika Mustermann', 'email' => 'erika@example.com', 'password' => 'erikas-passwort', 'password_confirmation' => 'erikas-passwort',
            'street' => 'Hauptstraße 1', 'zip' => '50667', 'city' => 'Köln', 'country' => 'DE', 'phone' => '0221 123', 'consent' => '1'])->assertRedirect(route('account.home'));
        $erika = User::where('email', 'erika@example.com')->firstOrFail();
        $this->assertSame('customer', $erika->role);
        $this->assertSame('Köln', $erika->billing_address['city']);
        $this->assertSame(1, app(Cart::class)->count()); // Warenkorb bleibt

        // Kasse ist vorausgefüllt
        $this->get('/kasse')->assertOk()->assertSee('value="erika@example.com"', false)->assertSee('value="Hauptstraße 1"', false)->assertDontSee('Schon Kunde?');

        // Abweichende Rechnungsadresse und Speichern im Konto
        $this->post('/kasse', ['email' => 'erika@example.com', 'name' => 'Erika Mustermann', 'street' => 'Lieferweg 5', 'zip' => '50667', 'city' => 'Köln', 'country' => 'DE',
            'payment_method' => 'prepayment', 'accept_terms' => '1', 'billing_different' => '1', 'save_address' => '1',
            'billing' => ['name' => 'Firma Mustermann GmbH', 'street' => 'Rechnungsstraße 9', 'zip' => '10115', 'city' => 'Berlin', 'country' => 'DE']]);
        $order = Order::firstOrFail();
        $this->assertSame($erika->id, $order->user_id);
        $this->assertSame('Rechnungsstraße 9', $order->billing['street']);
        $this->assertSame('Lieferweg 5', $erika->fresh()->shipping_address['street']);
        $this->assertSame('Rechnungsstraße 9', $erika->fresh()->billing_address['street']);

        $this->get('/konto')->assertOk()->assertSee('Bestellung '.$order->number)->assertSee('Zahlung offen');
        $this->get('/konto/daten')->assertOk()->assertSee('value="Rechnungsstraße 9"', false)->assertSee('value="Lieferweg 5"', false);
        $this->put('/konto/daten', ['name' => 'Erika M.', 'email' => 'erika@example.com', 'billing' => ['street' => 'Neue Straße 2', 'zip' => '10115', 'city' => 'Berlin', 'country' => 'DE'], 'shipping_same' => '1'])
            ->assertRedirect(route('account.edit'));
        $this->assertNull($erika->fresh()->shipping_address);
        $this->put('/konto/passwort', ['current_password' => 'falsch', 'password' => 'neues-passwort1', 'password_confirmation' => 'neues-passwort1'])->assertSessionHasErrors('current_password');

        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/kunden')->assertOk()->assertSee('Erika M.')->assertSee('Neue Straße 2');
        $this->actingAs($admin)->get('/admin/shop/bestellungen/'.$order->id)->assertOk()->assertSee('Rechnungsadresse:')->assertSee('Rechnungsstraße 9');
        $this->actingAs($admin)->get('/admin/shop/bestellungen/'.$order->id.'/rechnung')->assertOk()->assertSee('Firma Mustermann GmbH')->assertSee('Lieferung an:');

        // Kunden haben keinen Zugang zur Verwaltung oder zum Mitgliederbereich
        $this->actingAs($erika->fresh())->get('/admin')->assertForbidden();
        $this->get('/mitglieder')->assertRedirect(route('members.login'));

        // Konto löschen: Bestellung bleibt
        $this->delete('/konto', ['current_password' => 'erikas-passwort'])->assertRedirect(route('home'));
        $this->assertNull(User::find($erika->id));
        $this->assertNull($order->fresh()->user_id);
    }

    // ---------- Video-Vorschaubild ----------

    public function test_vorschaubild_fuer_youtube_video(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $img = imagecreatetruecolor(640, 360);
        ob_start();
        imagejpeg($img, null, 90);
        $jpg = ob_get_clean();
        Http::fake([
            'i.ytimg.com/vi/dQw4w9WgXcQ/maxresdefault.jpg' => Http::response('x', 404),
            'i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg' => Http::response($jpg, 200, ['Content-Type' => 'image/jpeg']),
        ]);
        $admin = $this->admin();
        $res = $this->actingAs($admin)->postJson('/admin/medien/video-vorschaubild', ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'])->assertOk();
        $url = $res->json('url');
        $this->assertStringContainsString('/uploads/', $url);
        $this->assertFileExists(public_path(parse_url($url, PHP_URL_PATH)));
        $this->actingAs($admin)->postJson('/admin/medien/video-vorschaubild', ['url' => 'https://example.com/film'])->assertUnprocessable();

        $page = Page::create(['title' => 'Film', 'slug' => 'film', 'status' => 'published', 'blocks' => Page::cleanBlocks([['id' => 'v', 'type' => 'video', 'data' => ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'poster' => $url]]])]);
        $this->assertSame($url, $page->blocks[0]['data']['poster']);
        auth()->logout();
        $this->get('/film')->assertOk()->assertSee("background-image: url('".$url."')", false)->assertDontSee('ytimg');
        @unlink(public_path(parse_url($url, PHP_URL_PATH)));
        unset($png);
    }

    // ---------- Module und Preise (Lizenzserver) ----------

    public function test_module_kostenlos_oder_kostenpflichtig_festlegen(): void
    {
        $pair = Signer::generateKeyPair();
        config(['nova.license.public_key' => $pair['public'], 'nova.license.private_key' => $pair['secret']]);
        app(NovaSalesSite::class)->install();
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/lizenzserver/preise')->assertOk()->assertSee('Datensicherung')->assertSee('39,00');
        $modules = collect(Features::ALL)->map(fn ($m, $k) => ['kind' => 'paid', 'price' => '49,00'])->all();
        $modules['datensicherung'] = ['kind' => 'free', 'price' => ''];
        $modules['turbo'] = ['kind' => 'paid', 'price' => ''];
        $this->actingAs($admin)->put('/admin/lizenzserver/preise', ['modules' => $modules])->assertSessionHasErrors('modules.turbo.price');
        $modules['turbo']['price'] = '19,90';
        $this->actingAs($admin)->put('/admin/lizenzserver/preise', ['modules' => $modules])->assertRedirect()->assertSessionHas('status');

        $this->assertSame(['datensicherung'], ModulePricing::freeKeys());
        $this->assertFalse(Product::where('slug', 'modul-datensicherung')->first()->is_active);
        $this->assertSame(1990, Product::where('slug', 'modul-turbo')->first()->price_cents);
        $this->get('/module')->assertOk()->assertSee('19,90 €');

        // Kostenlose Module gehören zu jeder Lizenz
        $issued = IssuedLicense::create(['key' => LicenseKey::generate(), 'customer_name' => 'Test', 'customer_email' => 't@example.com',
            'core' => true, 'modules' => ['newsletter'], 'max_domains' => 1, 'status' => 'active']);
        $res = app(LicenseServer::class)->activate(['key' => $issued->key, 'domain' => 'kunde.de', 'install_id' => str_repeat('a', 32)]);
        $payload = Signer::open($res['license']);
        $this->assertEqualsCanonicalizing(['newsletter', 'datensicherung'], $payload['modules']);
    }

    private function customerLicense(array $modules): void
    {
        $pair = Signer::generateKeyPair();
        config(['nova.license.public_key' => $pair['public'], 'nova.license.private_key' => '']);
        Setting::put('license_key', 'NOVA-TEST-KEY');
        $json = json_encode(['key' => 'NOVA-TEST-KEY', 'status' => 'active', 'install_id' => License::installId(),
            'valid_until' => now()->addYear()->getTimestamp(), 'core' => true, 'modules' => $modules]);
        Setting::put('license_payload', $json);
        Setting::put('license_signature', Signer::sign($json, $pair['secret']));
        $this->app->forgetScopedInstances();
        $this->app->forgetInstance(License::class);
        $this->app->forgetInstance(Features::class);
    }
}
