<?php

namespace App\Http\Controllers;

use App\Licensing\License;
use App\Models\Setting;
use App\Models\User;
use App\Support\Install\EnvWriter;
use App\Support\Install\InstallState;
use App\Support\Install\Requirements;
use App\Support\Security\Scanner;
use App\Support\Starter\NovaSalesSite;
use App\Support\Update\AutoUpdate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use PDO;
use Throwable;

/**
 * Installations-Assistent in vier Schritten:
 * 1. Systemprüfung  2. Datenbank  3. Website und Admin-Konto  4. Fertig
 */
class InstallController extends Controller
{
    public function welcome(Requirements $requirements): View
    {
        return view('install.welcome', [
            'checks' => $requirements->check(),
            'passes' => $requirements->passes(),
            'step' => 1,
        ]);
    }

    public function database(Requirements $requirements): View|RedirectResponse
    {
        if (! $requirements->passes()) {
            return redirect()->route('install.welcome');
        }

        return view('install.database', [
            'step' => 2,
            'sqliteAvailable' => extension_loaded('pdo_sqlite'),
            'mysqlAvailable' => extension_loaded('pdo_mysql'),
            'db' => session('install.db', ['driver' => extension_loaded('pdo_sqlite') ? 'sqlite' : 'mysql', 'host' => 'localhost', 'port' => '3306']),
        ]);
    }

    public function saveDatabase(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'driver' => ['required', 'in:sqlite,mysql'],
            'host' => ['required_if:driver,mysql', 'nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'database' => ['required_if:driver,mysql', 'nullable', 'string', 'max:64'],
            'username' => ['required_if:driver,mysql', 'nullable', 'string', 'max:128'],
            'password' => ['nullable', 'string', 'max:255'],
        ], [
            'host.required_if' => 'Bitte geben Sie den Datenbank-Server an (steht im Kundenbereich Ihres Hosters).',
            'database.required_if' => 'Bitte geben Sie den Namen der Datenbank an.',
            'username.required_if' => 'Bitte geben Sie den Datenbank-Benutzer an.',
        ]);

        if ($data['driver'] === 'mysql') {
            try {
                new PDO(
                    sprintf('mysql:host=%s;port=%d;dbname=%s', $data['host'], $data['port'] ?: 3306, $data['database']),
                    $data['username'],
                    $data['password'] ?? '',
                    [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
                );
            } catch (Throwable) {
                return back()->withInput()->withErrors([
                    'database' => 'Die Verbindung zur Datenbank hat nicht geklappt. Bitte prüfen Sie Server, Datenbankname, Benutzer und Passwort. Diese Angaben finden Sie im Kundenbereich Ihres Hosters unter "Datenbanken".',
                ]);
            }
        }

        session(['install.db' => $data]);

        return redirect()->route('install.site');
    }

    public function site(): View|RedirectResponse
    {
        if (! session()->has('install.db')) {
            return redirect()->route('install.database');
        }

        return view('install.site', ['step' => 3]);
    }

    public function finish(Request $request): RedirectResponse
    {
        $db = session('install.db');
        if (! $db) {
            return redirect()->route('install.database');
        }

        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'starter' => ['nullable', 'in:empty,nova-sales'],
        ], [
            'site_name.required' => 'Wie soll Ihre Website heißen?',
            'password.confirmed' => 'Die beiden Passwörter stimmen nicht überein.',
            'password.min' => 'Das Passwort braucht mindestens 10 Zeichen.',
        ]);

        try {
            $this->configureDatabase($db);
            Artisan::call('migrate', ['--force' => true]);
            AutoUpdate::remember();

            User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => 'admin',
            ]);
            Setting::putMany(['site_name' => $data['site_name'], 'installed_at' => now()->toIso8601String()]);
            License::installId();
            if (($data['starter'] ?? 'empty') === 'nova-sales') {
                app(NovaSalesSite::class)->install();
            }

            app(EnvWriter::class)->set([
                'APP_NAME' => $data['site_name'],
                'APP_URL' => $request->getSchemeAndHttpHost().$request->getBasePath(),
                'APP_ENV' => 'production',
                'APP_DEBUG' => false,
            ]);

            InstallState::markInstalled();
            Scanner::protectUploads();
            Scanner::storeHashes();
        } catch (Throwable $e) {
            report($e);

            return back()->withInput($request->except('password', 'password_confirmation'))->withErrors([
                'install' => 'Die Installation ist fehlgeschlagen: '.$e->getMessage(),
            ]);
        }

        session()->forget('install.db');

        return redirect()->route('install.done');
    }

    public function done(): View
    {
        return view('install.done', ['step' => 4]);
    }

    /** @param array<string, mixed> $db */
    private function configureDatabase(array $db): void
    {
        $writer = app(EnvWriter::class);

        if ($db['driver'] === 'sqlite') {
            $path = config('nova.sqlite_path');
            if (! file_exists($path)) {
                touch($path);
            }
            config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $path]);
            $writer->set(['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $path]);
        } else {
            config([
                'database.default' => 'mysql',
                'database.connections.mysql.host' => $db['host'],
                'database.connections.mysql.port' => $db['port'] ?: 3306,
                'database.connections.mysql.database' => $db['database'],
                'database.connections.mysql.username' => $db['username'],
                'database.connections.mysql.password' => $db['password'] ?? '',
            ]);
            $writer->set([
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $db['host'],
                'DB_PORT' => (string) ($db['port'] ?: 3306),
                'DB_DATABASE' => $db['database'],
                'DB_USERNAME' => $db['username'],
                'DB_PASSWORD' => $db['password'] ?? '',
            ]);
        }

        DB::purge();
        DB::reconnect();
    }
}
