<?php

namespace Tests\Feature;

use App\Mail\ContactReceived;
use App\Models\ContactMessage;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Shop\Cart;
use App\Support\Blocks\BlockSanitizer;
use App\Support\Starter\NovaSalesSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SalesSiteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_verkaufswebsite_wird_mit_seiten_menue_und_produkten_eingerichtet(): void
    {
        $old = Page::create(['title' => 'Alte Startseite', 'slug' => 'alt', 'status' => 'published', 'is_home' => true, 'blocks' => []]);

        $this->actingAs($this->admin())->post('/admin/startinhalt')->assertRedirect(route('admin.pages.index'));

        $this->assertTrue(NovaSalesSite::installed());
        $this->assertFalse($old->fresh()->is_home);
        foreach (['funktionen', 'module', 'vergleich', 'preise', 'fragen', 'testen', 'kontakt'] as $slug) {
            $this->assertTrue(Page::where('slug', $slug)->published()->exists(), $slug);
        }
        $this->assertTrue(Page::where('purpose', 'imprint')->exists());
        // 3 Lizenzen und 3 lieferbare Module (Design Pro, Newsletter, WhatsApp), 5 Module „bald verfügbar“
        $this->assertSame(6, Product::active()->count());
        $this->assertSame(5, Product::where('is_active', false)->count());
        $this->assertSame(['design-pro', 'whatsapp'], Product::where('slug', 'nova-cms-business')->first()->license_spec['modules']);
        $this->assertTrue(Product::where('slug', 'nova-cms-business')->first()->license_spec['core']);
        $this->assertSame(['terminbuchung'], Product::where('slug', 'modul-terminbuchung')->first()->license_spec['modules']);

        // Module und Vergleich hängen unter Funktionen
        $parent = MenuItem::where('page_id', Page::where('slug', 'funktionen')->value('id'))->first();
        $this->assertSame(2, MenuItem::where('parent_id', $parent->id)->count());

        // Zweiter Aufruf legt nichts doppelt an
        $this->actingAs($this->admin())->post('/admin/startinhalt');
        $this->assertSame(1, Page::where('slug', 'preise')->count());
    }

    public function test_verkaufsseiten_werden_angezeigt(): void
    {
        app(NovaSalesSite::class)->install();

        $home = $this->get('/')->assertOk();
        $home->assertSee('Die Website-Software, die man nicht erklären muss.')->assertSee('nova-tech-bg', false)
            ->assertSee('In vier Schritten zur eigenen Website')->assertSee('"@type":"FAQPage"', false);
        $this->assertMatchesRegularExpression('/Funktionen.*Module.*Vergleich.*Preise/s', $home->getContent());

        $this->get('/preise')->assertOk()->assertSee('149,00 €')->assertSee('349,00 €')->assertSee('Beliebteste Wahl')
            ->assertSee(route('shop.cart.add'), false)->assertSee('Fragen zu Lizenz und Preisen');
        $this->get('/module')->assertOk()->assertSee('Im Kern enthalten')->assertSee('Kostenloses Modul')
            ->assertSee('Bald verfügbar')->assertSee('data-filter="paid"', false);
        $this->get('/vergleich')->assertOk()->assertSee('Klassisches CMS (Grundinstallation)')->assertSee('Angaben ohne Gewähr');
        $this->get('/kontakt')->assertOk()->assertSee('Beratung zu Lizenzen')->assertSee('name="consent"', false);
        $this->get('/shop')->assertOk()->assertSee('Nova CMS Agentur')->assertSee('Newsletter')->assertDontSee('Terminbuchung');
    }

    public function test_modul_baukasten_legt_mehrere_produkte_in_den_warenkorb(): void
    {
        app(NovaSalesSite::class)->install();
        Product::whereIn('slug', ['modul-newsletter', 'modul-gutscheine'])->update(['is_active' => true]);

        $this->get('/module')->assertOk()->assertSee('data-builder', false)->assertSee('69,00 €');

        $ids = Product::whereIn('slug', ['modul-newsletter', 'modul-gutscheine'])->pluck('id')->all();
        $this->post('/warenkorb', ['products' => [...$ids, Product::where('slug', 'modul-terminbuchung')->value('id')]])
            ->assertRedirect(route('shop.cart'))->assertSessionHas('status', '2 Artikel liegen im Warenkorb.');
        $this->assertSame(2, app(Cart::class)->count());
        $this->post('/warenkorb', [])->assertSessionHasErrors('products');
    }

    public function test_kontaktformular_speichert_und_benachrichtigt(): void
    {
        Mail::fake();
        Setting::put('contact_email', 'info@example.com');
        $form = ['name' => 'Max', 'email' => 'max@example.com', 'subject' => 'Support', 'message' => 'Wie installiere ich Nova?', 'consent' => '1', 'block' => 'abc'];

        // Zu schnell abgeschickt oder verstecktes Feld ausgefüllt: still verworfen
        $this->post('/kontakt/nachricht', $form + ['_t' => encrypt(now()->getTimestamp())])->assertSessionHas('contact_sent', 'abc');
        $this->post('/kontakt/nachricht', $form + ['_t' => encrypt(now()->subMinute()->getTimestamp()), 'website' => 'spam'])->assertSessionHas('contact_sent');
        $this->assertSame(0, ContactMessage::count());

        $this->post('/kontakt/nachricht', $form + ['_t' => encrypt(now()->subMinute()->getTimestamp())])->assertSessionHas('contact_sent', 'abc');
        $this->assertSame(1, ContactMessage::count());
        Mail::assertSent(ContactReceived::class, fn ($m) => $m->hasTo('info@example.com') && $m->hasReplyTo('max@example.com'));

        $this->post('/kontakt/nachricht', ['name' => 'X', 'email' => 'x@example.com', 'message' => 'Hallo Welt', '_t' => encrypt(0)])
            ->assertSessionHasErrors('consent');

        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin')->assertSee('Anfragen');
        $msg = ContactMessage::first();
        $this->actingAs($admin)->get('/admin/anfragen')->assertOk()->assertSee('Wie installiere ich Nova?')->assertSee('Neu');
        $this->actingAs($admin)->patch('/admin/anfragen/'.$msg->id)->assertRedirect();
        $this->assertNotNull($msg->fresh()->read_at);
        $this->actingAs($admin)->delete('/admin/anfragen/'.$msg->id);
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_neue_bausteine_werden_bereinigt(): void
    {
        $clean = app(BlockSanitizer::class)->clean([
            ['id' => 'a', 'type' => 'features', 'data' => ['style' => 'x', 'items' => [['icon' => 'evil', 'title' => '<b>Hi</b>', 'text' => 't'], ['title' => '', 'text' => '']]]],
            ['id' => 'b', 'type' => 'pricing', 'data' => ['plans' => [['name' => 'P', 'features' => "a\n\nb", 'product' => '../x', 'button_url' => 'javascript:alert(1)']]]],
            ['id' => 'c', 'type' => 'modules', 'data' => ['items' => [['name' => 'M', 'kind' => 'hack']]]],
            ['id' => 'd', 'type' => 'compare', 'data' => ['rows' => [['label' => 'L', 'ours' => 'yes', 'theirs' => '<script>x</script>teils']]]],
            ['id' => 'e', 'type' => 'faq', 'data' => ['items' => [['q' => 'F?', 'a' => "Zeile 1\n<script>x</script>Zeile 2"], ['q' => 'leer', 'a' => '']]]],
            ['id' => 'f', 'type' => 'cta', 'data' => ['style' => 'pink', 'button_url' => 'www.beispiel.de']],
            ['id' => 'g', 'type' => 'hero', 'data' => ['variant' => 'tech', 'button2_url' => 'data:text/html,x']],
        ]);

        $this->assertSame(['icon' => 'sparkles', 'title' => 'Hi', 'text' => 't'], $clean[0]['data']['items'][0]);
        $this->assertCount(1, $clean[0]['data']['items']);
        $this->assertSame('grid', $clean[0]['data']['style']);
        $this->assertSame(['a', 'b'], $clean[1]['data']['plans'][0]['features']);
        $this->assertNull($clean[1]['data']['plans'][0]['product']);
        $this->assertNull($clean[1]['data']['plans'][0]['button_url']);
        $this->assertSame('core', $clean[2]['data']['items'][0]['kind']);
        $this->assertSame('xteils', $clean[3]['data']['rows'][0]['theirs']);
        $this->assertSame("Zeile 1\nxZeile 2", $clean[4]['data']['items'][0]['a']);
        $this->assertCount(1, $clean[4]['data']['items']);
        $this->assertSame(['dark', 'https://www.beispiel.de'], [$clean[5]['data']['style'], $clean[5]['data']['button_url']]);
        $this->assertSame(['tech', null], [$clean[6]['data']['variant'], $clean[6]['data']['button2_url']]);
    }

    public function test_editor_bietet_neue_bausteine_an(): void
    {
        $page = Page::create(['title' => 'X', 'slug' => 'x', 'status' => 'draft', 'blocks' => []]);
        $this->actingAs($this->admin())->get(route('admin.pages.edit', $page))->assertOk()
            ->assertSee('Überzeugen und verkaufen')->assertSee('Kontaktformular')->assertSee('Baukasten');
    }
}
