<?php

namespace Tests\Feature;

use App\Models\BlockedIp;
use App\Models\SecurityEvent;
use App\Models\Setting;
use App\Models\User;
use App\Support\Security\Scanner;
use App\Support\Security\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $attrs = []): User
    {
        return User::factory()->create(['role' => 'admin', 'password' => 'geheim12345', ...$attrs]);
    }

    private function withTwoFactor(User $user, array $recovery = []): User
    {
        $user->forceFill([
            'two_factor_secret' => Totp::generateSecret(),
            'two_factor_recovery_codes' => array_map(fn ($c) => password_hash($c, PASSWORD_DEFAULT), $recovery),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user->fresh();
    }

    public function test_totp_entspricht_rfc_6238(): void
    {
        // Testvektor aus RFC 6238 (SHA1, Schlüssel "12345678901234567890")
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $this->assertSame('287082', Totp::code($secret, 59));
        $this->assertSame('081804', Totp::code($secret, 1111111109));
        $this->assertTrue(Totp::verify($secret, Totp::code($secret)));
        $this->assertTrue(Totp::verify($secret, Totp::code($secret, time() - 30)));
        $this->assertFalse(Totp::verify($secret, Totp::code($secret, time() - 120)));
        $this->assertStringContainsString('<svg', Totp::qrSvg(Totp::uri($secret, 'a@b.de', 'Nova')));
    }

    public function test_fehlversuche_werden_protokolliert_und_sperren(): void
    {
        $user = $this->admin();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => $user->email, 'password' => 'falsch']);
        }
        $this->assertSame(5, SecurityEvent::where('type', 'login_failed')->count());
        $this->assertSame(1, SecurityEvent::where('type', 'lockout')->count());
    }

    public function test_anmeldung_mit_zwei_faktor(): void
    {
        $user = $this->withTwoFactor($this->admin());

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'geheim12345'])->assertRedirect(route('login.two-factor'));
        $this->assertGuest();
        $this->get('/admin')->assertRedirect(route('login'));

        $this->post('/admin/login/code', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/admin/login/code', ['code' => Totp::code($user->two_factor_secret)])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(SecurityEvent::where('type', 'login')->where('user_id', $user->id)->exists());
    }

    public function test_notfall_code_funktioniert_nur_einmal(): void
    {
        $user = $this->withTwoFactor($this->admin(), ['ABCD2345']);

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'geheim12345']);
        $this->post('/admin/login/code', ['code' => 'abcd-2345'])->assertRedirect(route('admin.dashboard'));
        $this->assertSame([], $user->fresh()->two_factor_recovery_codes);

        $this->post('/admin/logout');
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'geheim12345']);
        $this->post('/admin/login/code', ['code' => 'ABCD-2345'])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_zwei_faktor_zwischenschritt_verfaellt(): void
    {
        $user = $this->withTwoFactor($this->admin());
        $this->withSession(['nova.2fa' => ['id' => $user->id, 'remember' => false, 'at' => time() - 600]])
            ->post('/admin/login/code', ['code' => Totp::code($user->two_factor_secret)])
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_zwei_faktor_einrichten_und_ausschalten(): void
    {
        $user = $this->admin();
        $this->actingAs($user)->get('/admin/konto')->assertOk()->assertSee('Jetzt einrichten');

        $this->post('/admin/konto/zwei-faktor')->assertRedirect();
        $secret = session('nova.2fa_setup');
        $this->assertNotEmpty($secret);
        $this->get('/admin/konto')->assertSee('data-qr', false)->assertSee(substr($secret, 0, 4));

        $this->post('/admin/konto/zwei-faktor/bestaetigen', ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasTwoFactor());

        $this->post('/admin/konto/zwei-faktor/bestaetigen', ['code' => Totp::code($secret)])->assertSessionHas('nova.recovery_codes');
        $codes = session('nova.recovery_codes');
        $this->assertCount(8, $codes);
        $this->assertTrue($user->fresh()->hasTwoFactor());
        $this->assertTrue($user->fresh()->verifyTwoFactor($codes[0]));

        $this->delete('/admin/konto/zwei-faktor', ['current_password' => 'falsch'])->assertSessionHasErrors('current_password');
        $this->delete('/admin/konto/zwei-faktor', ['current_password' => 'geheim12345']);
        $this->assertFalse($user->fresh()->hasTwoFactor());
        $this->assertTrue(SecurityEvent::where('type', 'two_factor_disabled')->exists());
    }

    public function test_passwort_aendern(): void
    {
        $user = $this->admin();
        $this->actingAs($user)->put('/admin/konto/passwort', [
            'current_password' => 'falsch', 'password' => 'NeuesPasswort1', 'password_confirmation' => 'NeuesPasswort1',
        ])->assertSessionHasErrors('current_password');

        $this->put('/admin/konto/passwort', [
            'current_password' => 'geheim12345', 'password' => 'NeuesPasswort1', 'password_confirmation' => 'NeuesPasswort1',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(password_verify('NeuesPasswort1', $user->fresh()->password));
    }

    public function test_firewall_blockt_angriffe_und_sperrt_wiederholungstaeter(): void
    {
        $this->get('/?id=1%20UNION%20SELECT%20password%20FROM%20users')->assertForbidden();
        $this->get('/wp-login.php')->assertForbidden();
        $this->get('/?q=<script>alert(1)</script>')->assertForbidden();
        $this->get('/?datei=../../etc/passwd')->assertForbidden();
        $this->assertSame(4, SecurityEvent::where('type', 'attack')->count());

        // Normale Aufrufe gehen durch
        $this->get('/?suche=Brötchen und Kuchen')->assertOk();

        for ($i = 0; $i < 6; $i++) {
            $this->get('/.env');
        }
        $this->assertTrue(BlockedIp::isBlocked('127.0.0.1'));
        $this->get('/')->assertForbidden();

        BlockedIp::unblock('127.0.0.1');
        $this->get('/')->assertOk();
    }

    public function test_firewall_laesst_sich_ausschalten(): void
    {
        Setting::put('security_firewall', '0');
        $this->get('/wp-login.php')->assertNotFound();
    }

    public function test_formularinhalte_der_verwaltung_werden_nicht_blockiert(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/seiten', ['title' => 'Code-Beispiel <script>', 'template' => 'blank'])
            ->assertRedirect();
    }

    public function test_sicherheits_header(): void
    {
        $this->get('/')->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy');
    }

    public function test_sicherheits_check_findet_probleme_und_behebt_sie(): void
    {
        $user = $this->admin();
        File::ensureDirectoryExists(public_path('uploads/2026/11'));
        file_put_contents(public_path('uploads/2026/11/shell.php'), '<?php system($_GET["c"]);');
        File::ensureDirectoryExists(config('nova.plugins_path').'/boese');
        file_put_contents(config('nova.plugins_path').'/boese/plugin.php', '<?php eval(base64_decode("ZWNobyAxOw=="));');

        $this->actingAs($user)->post('/admin/sicherheit/pruefen')->assertRedirect(route('admin.security'));
        $checks = collect(Scanner::last()['checks'])->keyBy('key');

        $this->assertSame('bad', $checks['2fa']['status']);
        $this->assertSame('bad', $checks['uploads_scripts']['status']);
        $this->assertContains('uploads/2026/11/shell.php', $checks['uploads_scripts']['details']);
        $this->assertSame('bad', $checks['malware']['status']);
        $this->assertStringContainsString('plugins/boese/plugin.php', $checks['malware']['details'][0]);
        $this->assertSame('warn', $checks['integrity']['status']);
        $this->assertSame('warn', $checks['uploads_htaccess']['status']);

        $this->get('/admin/sicherheit')->assertOk()->assertSee('shell.php')->assertSee('Jetzt beheben');

        $this->post('/admin/sicherheit/beheben', ['what' => 'uploads']);
        $this->assertFileExists(public_path('uploads/.htaccess'));
        $this->post('/admin/sicherheit/beheben', ['what' => 'integrity']);
        $checks = collect(Scanner::last()['checks'])->keyBy('key');
        $this->assertSame('good', $checks['uploads_htaccess']['status']);
        $this->assertSame('good', $checks['integrity']['status']);
    }

    public function test_veraenderte_programmdateien_werden_erkannt(): void
    {
        Scanner::storeHashes();
        $stored = Scanner::storedHashes();
        $stored['app/Models/User.php'] = str_repeat('0', 64);
        unset($stored['routes/web.php']);
        $stored['app/Hintertuer.php'] = 'x';
        file_put_contents(Scanner::hashesPath(), json_encode($stored));

        $checks = collect(app(Scanner::class)->run()['checks'])->keyBy('key');
        $this->assertSame('bad', $checks['integrity']['status']);
        $this->assertContains('verändert: app/Models/User.php', $checks['integrity']['details']);
        $this->assertContains('neu: routes/web.php', $checks['integrity']['details']);
        $this->assertContains('fehlt: app/Hintertuer.php', $checks['integrity']['details']);
    }

    public function test_ips_von_hand_sperren_und_freigeben(): void
    {
        $this->actingAs($this->admin());
        $this->post('/admin/sicherheit/sperren', ['ip' => '127.0.0.1'])->assertSessionHasErrors('ip');
        $this->post('/admin/sicherheit/sperren', ['ip' => 'keine-ip'])->assertSessionHasErrors('ip');
        $this->post('/admin/sicherheit/sperren', ['ip' => '203.0.113.7', 'reason' => 'Spam'])->assertSessionHasNoErrors();
        $this->assertTrue(BlockedIp::isBlocked('203.0.113.7'));

        $this->get('/admin/sicherheit')->assertSee('203.0.113.7');
        $this->delete('/admin/sicherheit/sperren/'.BlockedIp::first()->id);
        $this->assertFalse(BlockedIp::isBlocked('203.0.113.7'));
    }

    public function test_hochladen_legt_schutzdatei_an(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/uploads', ['file' => UploadedFile::fake()->image('a.jpg'), 'kind' => 'image']);
        $this->assertStringContainsString('Require all denied', file_get_contents(public_path('uploads/.htaccess')));
    }

    public function test_konsolenbefehl(): void
    {
        $this->artisan('nova:security-scan --trust')->expectsOutputToContain('Ergebnis:')->assertExitCode(1);
    }
}
