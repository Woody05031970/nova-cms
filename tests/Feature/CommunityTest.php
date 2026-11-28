<?php

namespace Tests\Feature;

use App\Licensing\Features;
use App\Licensing\License;
use App\Licensing\Signer;
use App\Mail\CommunityMail;
use App\Models\CommunityComment;
use App\Models\CommunityConversation;
use App\Models\CommunityNotification;
use App\Models\CommunityPost;
use App\Models\CommunityProfile;
use App\Models\CommunityReport;
use App\Models\ForumBoard;
use App\Models\ForumTopic;
use App\Models\Setting;
use App\Models\User;
use App\Support\Community\Community;
use App\Support\Community\Text;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CommunityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::deleteDirectory(public_path('uploads/community'));
        parent::tearDown();
    }

    private function member(string $name = 'Anna Becker', string $role = 'member'): User
    {
        return User::factory()->create(['name' => $name, 'role' => $role]);
    }

    private function admin(): User
    {
        return User::factory()->create(['name' => 'Detlev Team', 'role' => 'admin']);
    }

    public function test_pinnwand_posten_kommentieren_gefaellt_mir_und_erwaehnen(): void
    {
        Mail::fake();
        $anna = $this->member();
        $ben = $this->member('Ben Müller');
        $benProfile = Community::profile($ben);
        $this->assertSame('ben_muller', $benProfile->handle);

        // Gäste lesen mit, dürfen aber nicht schreiben
        $this->get('/community')->assertOk()->assertSee('Pinnwand')->assertSee('Anmelden');
        $this->post('/community/beitraege', ['body' => 'Hallo'])->assertRedirect(route('members.login'));

        $this->actingAs($anna)->post('/community/beitraege', [
            'body' => 'Hallo zusammen, @ben_muller schau mal: https://example.org #garten **wichtig** <script>alert(1)</script>',
            'image' => UploadedFile::fake()->image('foto.jpg', 2400, 1600),
        ])->assertRedirect('/community');
        $post = CommunityPost::firstOrFail();
        $this->assertMatchesRegularExpression('#^/uploads/community/\d{4}/\d{2}/[a-z0-9]{20}\.jpg$#', $post->image);
        [$w] = getimagesize(public_path(ltrim($post->image, '/')));
        $this->assertSame(1600, $w);

        $html = $this->get('/community')->assertOk()->getContent();
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringContainsString('href="'.route('community.profile', 'ben_muller').'"', $html);
        $this->assertStringContainsString('<strong>wichtig</strong>', $html);
        $this->assertStringContainsString('rel="nofollow ugc noopener"', $html);

        // Erwähnung: Benachrichtigung und E-Mail an Ben
        $this->assertSame(1, CommunityNotification::where('user_id', $ben->id)->where('type', 'mention')->count());
        Mail::assertSent(CommunityMail::class, fn ($m) => $m->hasTo($ben->email));

        // Schlagwort-Filter
        $this->get('/community?tag=garten')->assertOk()->assertSee('#garten');
        $this->get('/community?tag=urlaub')->assertSee('Noch keine Beiträge');

        $this->actingAs($ben)->post("/community/beitrag/{$post->id}/gefaellt")->assertRedirect();
        $this->post("/community/beitrag/{$post->id}/kommentare", ['body' => 'Sieht super aus!'])->assertRedirect();
        $this->assertSame(1, $post->fresh()->likes_count);
        $this->assertSame(1, $post->fresh()->comments_count);
        $this->assertSame(2, CommunityNotification::where('user_id', $anna->id)->count());
        $this->get('/community/beitrag/'.$post->id)->assertOk()->assertSee('Sieht super aus!');

        // Noch mal klicken nimmt das „Gefällt mir“ zurück
        $this->post("/community/beitrag/{$post->id}/gefaellt");
        $this->assertSame(0, $post->fresh()->likes_count);

        // Fremde Beiträge darf man nicht löschen, eigene schon
        $this->delete('/community/beitrag/'.$post->id)->assertForbidden();
        $comment = CommunityComment::firstOrFail();
        $this->delete('/community/kommentar/'.$comment->id)->assertRedirect();
        $this->assertSame(0, $post->fresh()->comments_count);
        $this->actingAs($anna)->delete('/community/beitrag/'.$post->id)->assertRedirect();
        $this->assertFileDoesNotExist(public_path(ltrim($post->image, '/')));

        // Benachrichtigungen ansehen markiert sie als gelesen
        $this->assertSame(2, Community::counts($anna)['notifications']);
        $this->get('/community/benachrichtigungen')->assertOk()->assertSee('gefällt Ihr Beitrag');
        $this->assertSame(0, Community::counts($anna)['notifications']);
    }

    public function test_profil_folgen_und_eigene_seite_gestalten(): void
    {
        $anna = $this->member();
        $ben = $this->member('Ben Müller');
        $this->actingAs($anna)->get('/community/profil')->assertOk()->assertSee('Profil bearbeiten');
        $this->put('/community/profil', [
            'name' => 'Anna B.', 'handle' => 'anna', 'headline' => 'Gärtnerin', 'bio' => 'Ich mag Tomaten.', 'website' => 'https://anna.example',
            'avatar' => UploadedFile::fake()->image('a.png', 900, 600), 'messages_open' => '1',
        ])->assertRedirect('/community/leute/anna');
        $profile = $anna->fresh()->communityProfile;
        $this->assertSame('Anna B.', $anna->fresh()->name);
        [$w, $h] = getimagesize(public_path(ltrim($profile->avatar, '/')));
        $this->assertSame([512, 512], [$w, $h]);
        $this->assertFalse($profile->email_notifications);

        // Kurzname: nur gültige, freie Namen
        Community::profile($ben)->update(['handle' => 'ben']);
        $this->put('/community/profil', ['name' => 'Anna', 'handle' => 'ben'])->assertSessionHasErrors('handle');
        $this->put('/community/profil', ['name' => 'Anna', 'handle' => 'Anna!'])->assertSessionHasErrors('handle');
        $this->put('/community/profil', ['name' => 'Anna', 'handle' => 'admin'])->assertSessionHasErrors('handle');

        $this->get('/community/leute/anna')->assertOk()->assertSee('Gärtnerin')->assertSee('anna.example');

        // Folgen
        $this->actingAs($ben)->post('/community/leute/anna/folgen')->assertRedirect();
        $this->assertTrue(Community::follows($ben, $anna));
        $this->assertSame(1, CommunityNotification::where('user_id', $anna->id)->where('type', 'follow')->count());

        // Eigene Seite: Abschnitte hinzufügen, verschieben, entfernen, veröffentlichen
        $this->actingAs($anna)->get('/community/meine-seite')->assertOk()->assertSee('Abschnitt hinzufügen');
        $this->put('/community/meine-seite', ['page_title' => 'Annas Garten', 'op' => 'add:text'])->assertRedirect();
        $this->put('/community/meine-seite', ['page_title' => 'Annas Garten', 'page_color' => 'gruen', 'op' => 'add:links', 'sections' => [
            ['type' => 'text', 'heading' => 'Willkommen', 'text' => 'Hier zeige ich **meinen Garten**.'],
        ]]);
        $this->put('/community/meine-seite', ['page_title' => 'Annas Garten', 'page_color' => 'gruen', 'op' => 'add:video', 'sections' => [
            ['type' => 'text', 'heading' => 'Willkommen', 'text' => 'Hier zeige ich **meinen Garten**.'],
            ['type' => 'links', 'heading' => 'Mehr', 'text' => "Verein | https://verein.example\nkaputt | javascript:alert(1)\nhttps://shop.example"],
        ]]);
        $this->put('/community/meine-seite', ['page_title' => 'Annas Garten', 'page_color' => 'gruen', 'op' => 'up:2', 'sections' => [
            ['type' => 'text', 'heading' => 'Willkommen', 'text' => 'Hier zeige ich **meinen Garten**.'],
            ['type' => 'links', 'heading' => 'Mehr', 'text' => "Verein | https://verein.example\nkaputt | javascript:alert(1)\nhttps://shop.example"],
            ['type' => 'video', 'url' => 'https://youtu.be/dQw4w9WgXcQ', 'caption' => 'Rundgang'],
        ]]);
        $sections = $anna->fresh()->communityProfile->page_sections;
        $this->assertSame(['text', 'video', 'links'], array_column($sections, 'type'));
        $this->assertSame("Verein | https://verein.example\nshop.example | https://shop.example", $sections[2]['text']);
        $this->assertSame('dQw4w9WgXcQ', $sections[1]['video_id']);

        // Vor dem Veröffentlichen sieht nur Anna die Seite
        $this->actingAs($ben)->get('/community/leute/anna/seite')->assertNotFound();
        $this->actingAs($anna)->get('/community/leute/anna/seite')->assertOk()->assertSee('Vorschau');
        $this->put('/community/meine-seite', ['page_title' => 'Annas Garten', 'op' => 'publish', 'sections' => $sections]);
        $this->put('/community/meine-seite', ['page_title' => 'Annas Garten', 'op' => 'remove:2', 'sections' => $sections + [3 => ['type' => 'image', 'upload' => UploadedFile::fake()->image('beet.jpg', 800, 600)]]]);
        $sections = $anna->fresh()->communityProfile->page_sections;
        $this->assertSame(['text', 'video', 'image'], array_column($sections, 'type'));
        $this->assertNotNull($sections[2]['image']);
        auth()->logout();
        $this->get('/community/leute/anna/seite')->assertOk()->assertSee('Annas Garten')->assertSee('<strong>meinen Garten</strong>', false)
            ->assertSee('data-video-consent', false)->assertDontSee('Vorschau:');

        // Eigene Seiten abschaltbar
        Setting::put('community_pages', '0');
        $this->get('/community/leute/anna/seite')->assertNotFound();
    }

    public function test_persoenliche_nachrichten_mit_blockieren(): void
    {
        Mail::fake();
        $anna = $this->member();
        $ben = $this->member('Ben Müller');
        $benP = Community::profile($ben);
        $annaP = Community::profile($anna);

        $this->get('/community/nachrichten')->assertRedirect(route('members.login'));
        $this->actingAs($anna)->get('/community/nachrichten/'.$benP->handle)->assertOk()->assertSee('die erste Nachricht');
        $this->post('/community/nachrichten/'.$benP->handle, ['body' => 'Hallo Ben, hast du Zeit?'])->assertRedirect();
        $this->post('/community/nachrichten/'.$benP->handle, ['body' => 'Und noch etwas.']);
        // Nur die erste ungelesene Nachricht erzeugt eine E-Mail
        Mail::assertSent(CommunityMail::class, 1);
        $this->assertSame(1, Community::counts($ben)['messages']);

        $this->actingAs($ben)->get('/community/nachrichten')->assertOk()->assertSee('Anna Becker')->assertSee('Und noch etwas.');
        $this->get('/community/nachrichten/'.$annaP->handle)->assertOk()->assertSee('Hallo Ben, hast du Zeit?');
        $this->assertSame(0, Community::counts($ben)['messages']);

        // Dritte können das Gespräch nicht sehen
        $carla = $this->member('Carla');
        $this->actingAs($carla)->get('/community/nachrichten')->assertDontSee('Hallo Ben');

        // Ben blockiert Anna
        $this->actingAs($ben)->post('/community/leute/'.$annaP->handle.'/blockieren')->assertRedirect();
        $this->actingAs($anna)->post('/community/nachrichten/'.$benP->handle, ['body' => 'Hallo?'])->assertSessionHasErrors('body');
        $this->assertSame(2, CommunityConversation::firstOrFail()->messages()->count());

        // Nachrichten abgeschaltet
        $annaP->update(['messages_open' => false]);
        $this->actingAs($carla)->post('/community/nachrichten/'.$annaP->handle, ['body' => 'Hi'])->assertSessionHasErrors('body');
        $this->actingAs($this->admin())->post('/community/nachrichten/'.$annaP->handle, ['body' => 'Hinweis vom Team'])->assertSessionHasNoErrors();
    }

    public function test_foren_themen_antworten_und_moderation(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $anna = $this->member();
        $ben = $this->member('Ben Müller');

        $this->actingAs($admin)->get('/admin/community')->assertOk()->assertSee('Neues Forum');
        $this->post('/admin/community/foren', ['name' => 'Fragen und Hilfe', 'description' => 'Hier helfen sich alle.', 'position' => 1])->assertRedirect();
        $this->post('/admin/community/foren', ['name' => 'Ankündigungen', 'position' => 0, 'is_locked' => '1']);
        $help = ForumBoard::where('slug', 'fragen-und-hilfe')->firstOrFail();
        $news = ForumBoard::where('slug', 'ankundigungen')->firstOrFail();
        $this->assertTrue($news->is_locked);

        $this->actingAs($anna)->get('/community/foren')->assertOk()->assertSeeInOrder(['Ankündigungen', 'Fragen und Hilfe']);
        $this->get('/community/foren/'.$news->slug.'/neu')->assertForbidden();
        $this->post('/community/foren/'.$help->slug, ['title' => 'Wie dünge ich Tomaten?', 'body' => 'Hat jemand Tipps?'])->assertRedirect();
        $topic = ForumTopic::firstOrFail();
        $this->assertSame('wie-dunge-ich-tomaten', $topic->slug);
        $this->assertSame(1, $help->fresh()->topics_count);

        $this->actingAs($ben)->get('/community/thema/'.$topic->id)->assertRedirect($topic->url());
        $this->get($topic->url())->assertOk()->assertSee('Hat jemand Tipps?');
        $this->assertSame(1, $topic->fresh()->views_count);
        $this->post('/community/thema/'.$topic->id.'/antworten', ['body' => 'Brennnesseljauche!'])->assertRedirect();
        $this->assertSame(1, $topic->fresh()->replies_count);
        $this->assertSame($ben->id, $topic->fresh()->last_user_id);
        $this->assertSame(1, CommunityNotification::where('user_id', $anna->id)->where('type', 'reply')->count());
        Mail::assertSent(CommunityMail::class, fn ($m) => $m->hasTo($anna->email));

        // Anna darf ihr Thema nicht mehr löschen, wenn es Antworten gibt
        $this->actingAs($anna)->delete('/community/thema/'.$topic->id)->assertForbidden();

        // Admin schließt das Thema
        $this->actingAs($admin)->put('/community/thema/'.$topic->id, ['toggle' => 'lock'])->assertRedirect();
        $this->actingAs($anna)->post('/community/thema/'.$topic->id.'/antworten', ['body' => 'Danke!'])->assertForbidden();
        $this->get($topic->url())->assertSee('Dieses Thema ist geschlossen');

        // Forum löschen entfernt alle Themen
        $this->actingAs($admin)->delete('/admin/community/foren/'.$help->slug)->assertRedirect();
        $this->assertSame(0, ForumTopic::count());
    }

    public function test_melden_sperren_und_nur_fuer_mitglieder(): void
    {
        $admin = $this->admin();
        $anna = $this->member();
        $troll = $this->member('Troll');
        $post = CommunityPost::create(['user_id' => $troll->id, 'body' => 'Kauft billige Uhren!']);
        Community::profile($troll);

        $this->actingAs($anna)->post('/community/melden', ['type' => 'post', 'id' => $post->id, 'reason' => 'Werbung'])->assertRedirect();
        $this->post('/community/melden', ['type' => 'post', 'id' => 99999, 'reason' => 'x'])->assertNotFound();
        $this->assertSame(1, Community::openReports());

        $this->actingAs($admin)->get('/admin/community')->assertOk()->assertSee('Kauft billige Uhren!')->assertSee('Werbung');
        $this->get('/admin')->assertSee('Community');
        $report = CommunityReport::firstOrFail();
        $this->put('/admin/community/meldungen/'.$report->id, ['action' => 'delete'])->assertRedirect();
        $this->assertNull(CommunityPost::find($post->id));
        $this->assertSame(0, Community::openReports());

        // Sperren: kann nicht mehr schreiben, Profil verschwindet
        $this->put('/admin/community/sperren/'.$troll->id)->assertRedirect();
        $this->assertTrue($troll->fresh()->communityProfile->isBanned());
        $this->actingAs($troll->fresh())->post('/community/beitraege', ['body' => 'Noch mal'])->assertForbidden();
        auth()->logout();
        $this->get('/community/leute/'.$troll->communityProfile->handle)->assertNotFound();
        $this->actingAs($admin)->put('/admin/community/sperren/'.$troll->id);
        $this->assertFalse($troll->fresh()->communityProfile->isBanned());

        // Nur für Mitglieder: Gäste werden zur Anmeldung geschickt
        $this->put('/admin/community/einstellungen', ['community_visibility' => 'members', 'members_registration' => 'open', 'community_title' => 'Gartenfreunde', 'community_pages' => '1'])->assertRedirect();
        auth()->logout();
        $this->get('/community/foren')->assertRedirect(route('members.login'));
        $this->get(route('members.login'))->assertSee('Gartenfreunde');
        $this->assertSame(route('community.forums'), session('members.intended'));

        // Wartende Mitglieder und Kunden kommen nicht hinein
        $this->actingAs($this->member('Neu', 'member_pending'))->get('/community')->assertForbidden();
    }

    public function test_registrieren_nur_mit_community_modul_und_konto_loeschen(): void
    {
        $this->customerLicense(['community']);
        $this->assertTrue(Features::on('community'));
        $this->assertFalse(Features::on('mitgliederbereich'));
        Setting::put('members_registration', 'open');

        $this->get('/community/leute')->assertOk();
        $this->get('/mitglieder/registrieren')->assertOk();
        $this->post('/mitglieder/registrieren', ['name' => 'Clara Neu', 'email' => 'clara@example.com', 'password' => 'GartenFreude2026', 'password_confirmation' => 'GartenFreude2026', 'consent' => '1'])
            ->assertRedirect(route('community.home'));
        $clara = User::where('email', 'clara@example.com')->firstOrFail();
        $this->get('/mitglieder')->assertRedirect(route('community.home'));
        $this->post('/community/beitraege', ['body' => 'Hallo, ich bin neu!'])->assertRedirect();
        $this->assertSame(1, CommunityPost::where('user_id', $clara->id)->count());
        $this->assertNotSame(200, $this->get('/admin/mitglieder')->status());

        // Konto löschen entfernt alles
        $this->delete('/community/konto', ['password' => 'falsch'])->assertSessionHasErrors('password');
        $this->delete('/community/konto', ['password' => 'GartenFreude2026'])->assertRedirect(route('home'));
        $this->assertNull(User::find($clara->id));
        $this->assertSame(0, CommunityPost::count());
        $this->assertSame(0, CommunityProfile::count());
        $this->assertGuest();
    }

    public function test_ohne_modul_keine_community(): void
    {
        $this->customerLicense(['mitgliederbereich']);
        $this->get('/community')->assertNotFound();
        $this->actingAs($this->admin())->get('/admin/community')->assertForbidden();
    }

    public function test_text_darstellung_ist_sicher(): void
    {
        $html = (string) Text::render('<img src=x onerror=alert(1)> "https://a.example/?q=<b>" javascript:alert(1) **x** a@b.de #tag');
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringNotContainsString('href="javascript', $html);
        $this->assertStringContainsString('<strong>x</strong>', $html);
        $this->assertStringNotContainsString('community/leute/b.de', $html); // E-Mail ist keine Erwähnung
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
