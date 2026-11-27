<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmation;
use App\Mail\OrderReceived;
use App\Mail\OrderShipped;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Shop\Cart;
use App\Shop\Money;
use App\Shop\ShopSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Setting::putMany([
            'site_name' => 'Sonnenkorn', 'company_name' => 'Sonnenkorn GmbH', 'contact_email' => 'shop@example.com',
            'shop_enabled' => '1', 'shop_iban' => 'DE89 3704 0044 0532 0130 00', 'shop_shipping_cents' => '490', 'shop_free_from_cents' => '5000',
        ]);
    }

    private function product(array $attrs = []): Product
    {
        return Product::create([
            'name' => 'Holzofenbrot', 'slug' => 'holzofenbrot-'.uniqid(), 'price_cents' => 590, 'tax_rate' => 7,
            'is_active' => true, ...$attrs,
        ]);
    }

    private function customer(array $extra = []): array
    {
        return [
            'email' => 'kunde@example.com', 'name' => 'Erika Mustermann', 'street' => 'Hauptstraße 1', 'zip' => '50667',
            'city' => 'Köln', 'country' => 'DE', 'payment_method' => 'prepayment', 'accept_terms' => '1', ...$extra,
        ];
    }

    public function test_geldbetraege(): void
    {
        $this->assertSame('1.234,50 €', Money::format(123450));
        $this->assertSame(1290, Money::parse('12,90'));
        $this->assertSame(1290, Money::parse('12.90'));
        $this->assertSame(123450, Money::parse('1.234,50'));
        $this->assertNull(Money::parse('zwölf'));
        $this->assertSame(19, Money::includedTax(119, 19));
    }

    public function test_shop_ist_ausgeschaltet_unsichtbar(): void
    {
        Setting::put('shop_enabled', '0');
        $this->get('/shop')->assertNotFound();
        $this->get('/warenkorb')->assertNotFound();
        $this->get('/')->assertDontSee('Warenkorb');
    }

    public function test_produktseite_zeigt_pflichtangaben(): void
    {
        $p = $this->product(['base_quantity' => 750, 'base_unit' => 'g', 'stock' => 3, 'description' => '<p>Mit Sauerteig</p>']);
        Page::create(['title' => 'Versand und Zahlung', 'slug' => 'versand', 'purpose' => 'shipping', 'blocks' => [], 'status' => 'published']);

        $this->get('/shop')->assertOk()->assertSee('Holzofenbrot')->assertSee('5,90 €');
        $this->get($p->url())->assertOk()
            ->assertSee('inkl. 7 % MwSt.')
            ->assertSee('Versandkosten')
            ->assertSee('href="'.url('/versand').'"', false)
            ->assertSee('Grundpreis: 7,87 € / 1 kg')
            ->assertSee('nur noch 3 Stück')
            ->assertSee('"@type":"Product"', false);
    }

    public function test_warenkorb_und_steuern(): void
    {
        $brot = $this->product(['price_cents' => 1070, 'tax_rate' => 7]);
        $tasse = $this->product(['name' => 'Tasse', 'price_cents' => 1190, 'tax_rate' => 19]);

        $this->post('/warenkorb', ['product' => $brot->id, 'quantity' => 2])->assertRedirect('/warenkorb');
        $this->post('/warenkorb', ['product' => $tasse->id]);
        $this->get('/warenkorb')->assertOk()->assertSee('Holzofenbrot')->assertSee('Tasse');

        $totals = app(Cart::class)->totals();
        $this->assertSame(3330, $totals['subtotal']);
        $this->assertSame(490, $totals['shipping']);
        $this->assertSame(3820, $totals['total']);
        // Versand anteilig: 2140/3330 zu 7 %, Rest zu 19 %
        $this->assertSame([['rate' => 19.0, 'cents' => 218], ['rate' => 7.0, 'cents' => 161]], $totals['taxes']);
        $this->assertSame(1670, $totals['free_shipping_missing']);

        $this->patch('/warenkorb', ['quantities' => [$brot->id => 5]]);
        $this->assertSame(0, app(Cart::class)->totals()['shipping']);
        $this->patch('/warenkorb', ['remove' => $tasse->id]);
        $this->assertSame(5, app(Cart::class)->count());
    }

    public function test_kleinunternehmer_ohne_mwst(): void
    {
        Setting::put('shop_small_business', '1');
        $p = $this->product();
        $this->get($p->url())->assertSee('ohne MwSt. (Kleinunternehmer)');
        $this->post('/warenkorb', ['product' => $p->id]);
        $this->assertSame([], app(Cart::class)->totals()['taxes']);
        $this->get('/kasse')->assertSee('Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.');
    }

    public function test_bestellung_mit_vorkasse(): void
    {
        $p = $this->product(['stock' => 5]);
        Page::create(['title' => 'Widerrufsbelehrung', 'slug' => 'widerruf', 'purpose' => 'withdrawal', 'status' => 'published',
            'blocks' => [['id' => 'a1', 'type' => 'text', 'data' => ['html' => '<p>Sie haben das Recht, binnen vierzehn Tagen …</p>']]]]);

        $this->post('/warenkorb', ['product' => $p->id, 'quantity' => 2]);
        $this->get('/kasse')->assertOk()->assertSee('Zahlungspflichtig bestellen')->assertSee('Vorkasse (Überweisung)')->assertSee('Widerrufsbelehrung');

        // Ohne AGB-Häkchen geht es nicht
        $this->post('/kasse', $this->customer(['accept_terms' => null]))->assertSessionHasErrors('accept_terms');
        // Nur erlaubte Lieferländer
        $this->post('/kasse', $this->customer(['country' => 'US']))->assertSessionHasErrors('country');

        $res = $this->post('/kasse', $this->customer());
        $order = Order::firstOrFail();
        $res->assertRedirect(route('shop.order', $order->token));

        $this->assertSame(now()->format('Y').'-0001', $order->number);
        $this->assertSame(1180 + 490, $order->total_cents);
        $this->assertSame('open', $order->payment_status);
        $this->assertNotNull($order->terms_accepted_at);
        $this->assertSame(3, $p->fresh()->stock);
        $this->assertSame(0, app(Cart::class)->count());

        $this->get(route('shop.order', $order->token))->assertOk()
            ->assertSee($order->number)->assertSee('DE89 3704 0044 0532 0130 00')->assertSee('Bestellung '.$order->number);

        Mail::assertSent(OrderConfirmation::class, function ($m) use ($order) {
            $html = $m->render();

            return $m->hasTo('kunde@example.com') && str_contains($html, $order->number) && str_contains($html, 'binnen vierzehn Tagen');
        });
        Mail::assertSent(OrderReceived::class, fn ($m) => $m->hasTo('shop@example.com'));
    }

    public function test_bestand_wird_geprueft(): void
    {
        $p = $this->product(['stock' => 1]);
        $this->post('/warenkorb', ['product' => $p->id, 'quantity' => 3]);
        $this->get('/warenkorb')->assertSee('Nur noch 1 Stück vorrätig');
        $this->assertSame(590, app(Cart::class)->totals()['subtotal']);

        // Jemand anderes kauft zwischendurch das letzte Stück
        $p->update(['stock' => 0]);
        $this->post('/kasse', $this->customer())->assertRedirect('/warenkorb');
        $this->assertSame(0, Order::count());

        $this->post('/warenkorb', ['product' => $p->id])->assertSessionHasErrors('quantity');
    }

    public function test_preisaenderung_waehrend_der_bestellung(): void
    {
        $p = $this->product();
        $this->post('/warenkorb', ['product' => $p->id]);
        $this->get('/kasse')->assertOk();
        // Der Warenkorb liest Preise immer frisch: neue Summe wird übernommen
        $p->update(['price_cents' => 990]);
        $this->post('/kasse', $this->customer());
        $this->assertSame(990 + 490, Order::first()->total_cents);
    }

    public function test_bezahlung_mit_stripe(): void
    {
        ShopSettings::putSecret('shop_stripe_secret', 'sk_test_123');
        Setting::put('shop_pay_stripe', '1');
        $p = $this->product(['price_cents' => 6000]);

        Http::fake([
            'api.stripe.com/v1/checkout/sessions/cs_test_1' => Http::response(['id' => 'cs_test_1', 'payment_status' => 'paid', 'amount_total' => 6000, 'client_reference_id' => now()->format('Y').'-0001']),
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1']),
        ]);

        $this->post('/warenkorb', ['product' => $p->id]);
        $this->post('/kasse', $this->customer(['payment_method' => 'stripe']))->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');
        $order = Order::first();
        $this->assertSame('cs_test_1', $order->payment_reference);

        Http::assertSent(fn ($r) => $r->url() === 'https://api.stripe.com/v1/checkout/sessions'
            && $r->hasHeader('Authorization', 'Bearer sk_test_123')
            && $r['line_items'][0]['price_data']['unit_amount'] === 6000
            && $r['line_items'][0]['price_data']['currency'] === 'eur');

        // Gefälschte Rückkehr mit fremder Sitzung zählt nicht
        $this->get(route('shop.payment.return', [$order->token, 'stripe']).'?session_id=cs_fremd')->assertSessionHasErrors('payment');
        $this->assertFalse($order->fresh()->isPaid());

        $this->get(route('shop.payment.return', [$order->token, 'stripe']).'?session_id=cs_test_1')->assertRedirect(route('shop.order', $order->token));
        $this->assertTrue($order->fresh()->isPaid());
        $this->assertSame('processing', $order->fresh()->status);
    }

    public function test_bezahlung_mit_paypal(): void
    {
        Setting::putMany(['shop_pay_paypal' => '1', 'shop_paypal_client' => 'client-1', 'shop_paypal_sandbox' => '1']);
        ShopSettings::putSecret('shop_paypal_secret', 'secret-1');
        $p = $this->product(['price_cents' => 1250]);

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            'api-m.sandbox.paypal.com/v2/checkout/orders/PP-1/capture' => Http::response(['status' => 'COMPLETED',
                'purchase_units' => [['payments' => ['captures' => [['amount' => ['value' => '17.40', 'currency_code' => 'EUR']]]]]]]),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response(['id' => 'PP-1', 'links' => [['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PP-1']]]),
        ]);

        $this->post('/warenkorb', ['product' => $p->id]);
        $this->post('/kasse', $this->customer(['payment_method' => 'paypal']))->assertRedirect('https://www.sandbox.paypal.com/checkoutnow?token=PP-1');
        $order = Order::first();
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v2/checkout/orders') && $r['purchase_units'][0]['amount']['value'] === '17.40');

        $this->get(route('shop.payment.return', [$order->token, 'paypal']).'?token=PP-1');
        $this->assertTrue($order->fresh()->isPaid());
    }

    public function test_abgebrochene_zahlung_kann_wiederholt_werden(): void
    {
        ShopSettings::putSecret('shop_stripe_secret', 'sk_test_123');
        Setting::put('shop_pay_stripe', '1');
        Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'down']], 500)]);
        $p = $this->product();

        $this->post('/warenkorb', ['product' => $p->id]);
        $order = null;
        $res = $this->post('/kasse', $this->customer(['payment_method' => 'stripe']));
        $order = Order::first();
        $res->assertRedirect(route('shop.order', $order->token))->assertSessionHasErrors('payment');
        $this->get(route('shop.order', $order->token))->assertSee('Jetzt bezahlen');
    }

    public function test_unbekannte_bestellung(): void
    {
        $this->get('/bestellung/gibt-es-nicht')->assertNotFound();
    }

    public function test_verwaltung_produkte(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/admin/shop')->assertOk()->assertSee('Noch keine Bestellungen');
        $this->get('/admin/shop/produkte/neu')->assertOk()->assertSee('Produkt anlegen');

        $this->post('/admin/shop/produkte', [
            'name' => 'Dinkel Vollkorn', 'price' => '4,80', 'tax_rate' => '7', 'stock' => '10', 'is_active' => '1',
            'description' => '<p>Lecker</p><script>alert(1)</script>', 'images' => ['/uploads/2026/11/brot.jpg', 'javascript:alert(1)'],
            'base_quantity' => '0,5', 'base_unit' => 'kg',
        ])->assertRedirect();

        $p = Product::firstOrFail();
        $this->assertSame('dinkel-vollkorn', $p->slug);
        $this->assertSame(480, $p->price_cents);
        $this->assertSame(['/uploads/2026/11/brot.jpg'], $p->images);
        $this->assertStringNotContainsString('script', $p->description);
        $this->assertSame('9,60 € / 1 kg', $p->basePrice());

        $this->post('/admin/shop/produkte', ['name' => 'Falsch', 'price' => 'abc', 'tax_rate' => '7'])->assertSessionHasErrors('price_cents');
        $this->post('/admin/shop/produkte', ['name' => 'Falsch', 'price' => '1', 'tax_rate' => '16'])->assertSessionHasErrors('tax_rate');

        $this->get('/admin/shop/produkte')->assertSee('Dinkel Vollkorn')->assertSee('10 auf Lager');
        $this->delete('/admin/shop/produkte/'.$p->id);
        $this->assertSame(0, Product::count());
    }

    public function test_verwaltung_bestellung_bearbeiten(): void
    {
        $p = $this->product(['stock' => 5]);
        $this->post('/warenkorb', ['product' => $p->id, 'quantity' => 2]);
        $this->post('/kasse', $this->customer());
        $order = Order::first();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/admin/shop')->assertSee($order->number)->assertSee('Erika Mustermann');
        $this->get('/admin/shop/bestellungen/'.$order->id)->assertOk()->assertSee('Zahlung ist eingegangen');

        $this->put('/admin/shop/bestellungen/'.$order->id, ['action' => 'paid']);
        $this->assertTrue($order->fresh()->isPaid());

        $this->put('/admin/shop/bestellungen/'.$order->id, ['action' => 'shipped', 'tracking_number' => '00340434', 'notify' => '1']);
        $this->assertSame('shipped', $order->fresh()->status);
        Mail::assertSent(OrderShipped::class, fn ($m) => str_contains($m->render(), '00340434'));

        $this->get('/admin/shop/bestellungen/'.$order->id.'/rechnung')->assertOk()
            ->assertSee('Rechnung')->assertSee($order->number)->assertSee('MwSt. 7 %')->assertSee('Sonnenkorn GmbH');

        // Produkte mit Bestellungen werden nur ausgeblendet
        $this->delete('/admin/shop/produkte/'.$p->id);
        $this->assertFalse($p->fresh()->is_active);
    }

    public function test_storno_bucht_bestand_zurueck(): void
    {
        $p = $this->product(['stock' => 5]);
        $this->post('/warenkorb', ['product' => $p->id, 'quantity' => 2]);
        $this->post('/kasse', $this->customer());
        $this->assertSame(3, $p->fresh()->stock);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->put('/admin/shop/bestellungen/'.Order::first()->id, ['action' => 'cancelled']);
        $this->assertSame(5, $p->fresh()->stock);
        $this->assertSame('cancelled', Order::first()->status);
        $this->put('/admin/shop/bestellungen/'.Order::first()->id, ['action' => 'paid'])->assertSessionHasErrors('action');
    }

    public function test_einstellungen_und_geheime_schluessel(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get('/admin/shop/einstellungen')->assertOk()->assertSee('Zahlungsarten');

        $this->put('/admin/shop/einstellungen', [
            'enabled' => '1', 'country' => 'AT', 'ship_to' => ['AT', 'DE'], 'shipping' => '5,90', 'free_from' => '',
            'pay_prepayment' => '1', 'iban' => 'at611904300234573201', 'pay_stripe' => '1', 'stripe_secret' => 'sk_live_abc',
        ])->assertSessionHasNoErrors();

        $this->assertSame('AT61 1904 3002 3457 3201', Setting::get('shop_iban'));
        $this->assertSame([20, 13, 10, 0], ShopSettings::taxRates());
        $this->assertSame(590, ShopSettings::shippingCents());
        $this->assertNull(ShopSettings::freeShippingFromCents());
        $this->assertSame('sk_live_abc', ShopSettings::secret('shop_stripe_secret'));
        $this->assertStringNotContainsString('sk_live_abc', (string) Setting::get('shop_stripe_secret'));
        $this->get('/admin/shop/einstellungen')->assertDontSee('sk_live_abc');

        // Leeres Feld lässt den gespeicherten Schlüssel stehen
        $this->put('/admin/shop/einstellungen', ['enabled' => '1', 'country' => 'AT', 'ship_to' => ['AT'], 'shipping' => '5,90', 'stripe_secret' => '']);
        $this->assertSame('sk_live_abc', ShopSettings::secret('shop_stripe_secret'));

        $this->put('/admin/shop/einstellungen', ['country' => 'DE', 'ship_to' => ['DE'], 'shipping' => '1', 'stripe_secret' => 'falsch'])->assertSessionHasErrors('stripe_secret');
    }

    public function test_rechtstext_vorlagen(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['agb' => 'terms', 'widerruf' => 'withdrawal', 'versand' => 'shipping'] as $tpl => $purpose) {
            $this->post('/admin/seiten', ['template' => $tpl])->assertRedirect();
            $this->assertTrue(Page::where('purpose', $purpose)->exists(), $tpl);
        }
        $this->assertStringContainsString('Muster-Widerrufsformular', json_encode(Page::where('purpose', 'withdrawal')->first()->blocks, JSON_UNESCAPED_UNICODE));
        $this->assertStringContainsString('Vorkasse', json_encode(Page::where('purpose', 'shipping')->first()->blocks, JSON_UNESCAPED_UNICODE));
    }

    public function test_dashboard_hinweise_und_sitemap(): void
    {
        $this->product(['slug' => 'roggenbrot']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin')->assertSee('AGB veröffentlichen');
        $this->get('/sitemap.xml')->assertSee(url('/shop/roggenbrot'));
    }

    public function test_ki_schreibt_produkttext(): void
    {
        Setting::putMany(['ai_provider' => 'anthropic', 'ai_model' => 'claude-opus-5-5', 'ai_key' => \App\Ai\AiManager::encryptKey('k')]);
        app(\App\Ai\AiManager::class)->fake(new class implements \App\Ai\AiDriver
        {
            public function complete(string $system, string $prompt, ?array $schema = null, int $maxTokens = 4000): string
            {
                return json_encode(['short' => 'Knuspriges Brot aus dem Holzofen.', 'html' => '<p>Lecker</p><script>x</script>']);
            }
        });
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson('/admin/ki/produkt', ['name' => 'Holzofenbrot', 'notes' => 'Sauerteig'])
            ->assertOk()->assertJson(['short' => 'Knuspriges Brot aus dem Holzofen.', 'html' => '<p>Lecker</p>']);
    }

    public function test_email_versand_einrichten(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get('/admin/einstellungen')->assertOk()->assertSee('E-Mail-Versand');
        $this->put('/admin/einstellungen/email', ['mail_mode' => 'smtp', 'mail_host' => ''])->assertSessionHasErrors('mail_host');
        $this->put('/admin/einstellungen/email', [
            'mail_mode' => 'smtp', 'mail_host' => 'smtp.ionos.de', 'mail_port' => '465', 'mail_user' => 'info@sonnenkorn.de',
            'mail_password' => 'geheim', 'mail_from' => 'info@sonnenkorn.de',
        ])->assertSessionHasNoErrors();
        $this->assertSame('geheim', ShopSettings::secret('mail_password'));
        $this->assertNotSame('geheim', Setting::get('mail_password'));

        // Beim nächsten Start werden die Einstellungen übernommen
        $this->refreshApplication();
        $this->app->usePublicPath(sys_get_temp_dir());
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.ionos.de', config('mail.mailers.smtp.host'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('geheim', config('mail.mailers.smtp.password'));
        $this->assertSame('info@sonnenkorn.de', config('mail.from.address'));
    }

    public function test_test_email(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'email' => 'chef@example.com']))
            ->post('/admin/einstellungen/email/test')->assertSessionHas('status');
    }
}
