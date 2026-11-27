<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'password' => 'geheim12345']);
    }

    public function test_gaeste_werden_zur_anmeldung_geleitet(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_anmeldung_und_abmeldung(): void
    {
        $user = $this->admin();

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'falsch'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'geheim12345'])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->post('/admin/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_sperre_nach_fuenf_fehlversuchen(): void
    {
        $user = $this->admin();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => $user->email, 'password' => 'falsch']);
        }
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'geheim12345'])
            ->assertSessionHasErrors(['email' => 'Zu viele Fehlversuche. Bitte warten Sie 5 Minute(n).']);
        $this->assertGuest();
    }

    public function test_redakteure_ohne_adminrechte_werden_abgewiesen(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'editor']))->get('/admin')->assertForbidden();
    }

    public function test_dashboard_zeigt_fehlende_angaben_und_gesundheitswert(): void
    {
        Setting::put('site_name', 'Testseite');

        $this->actingAs($this->admin())->get('/admin')
            ->assertOk()
            ->assertSee('Website-Gesundheit')
            ->assertSee('Kurzbeschreibung ergänzen')
            ->assertSee('2 von 11 Punkten erledigt');
    }

    public function test_einstellungen_speichern_erhoeht_den_gesundheitswert(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put('/admin/einstellungen', [
            'site_name' => 'Bäckerei Müller', 'tagline' => 'Brot seit 1952', 'industry' => 'Bäckerei',
            'company_name' => 'Müller GmbH', 'street' => 'Hauptstr. 1', 'zip_city' => '50667 Köln',
            'phone' => '0221 123', 'contact_email' => 'info@example.com',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->get('/admin')->assertDontSee('Kurzbeschreibung ergänzen')->assertSee('6 von 11 Punkten erledigt');
        $this->get('/')->assertSee('Bäckerei Müller')->assertSee('Brot seit 1952');
    }
}
