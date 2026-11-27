<?php

namespace App\Http\Controllers\Admin\Licensing;

use App\Http\Controllers\Controller;
use App\Licensing\LicenseServer;
use App\Models\IssuedLicense;
use App\Models\LicenseActivation;
use App\Models\MarketModule;
use App\Plugins\PluginException;
use App\Plugins\PluginManifest;
use App\Shop\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use ZipArchive;

/**
 * Verwaltung des Lizenzservers: Lizenzen ausgeben und Module im Marktplatz anbieten.
 * Nur sichtbar, wenn diese Installation als Lizenzserver eingerichtet ist.
 */
class ServerController extends Controller
{
    public function __construct()
    {
        abort_unless(LicenseServer::enabled(), 404);
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('suche'));

        return view('admin.license.server', [
            'licenses' => IssuedLicense::withCount('activations')
                ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('key', 'like', "%{$q}%")->orWhere('customer_email', 'like', "%{$q}%")->orWhere('customer_name', 'like', "%{$q}%")))
                ->latest('id')->paginate(25)->withQueryString(),
            'modules' => MarketModule::orderBy('name')->get(),
            'search' => $q,
            'stats' => [
                'licenses' => IssuedLicense::where('status', 'active')->count(),
                'sites' => LicenseActivation::count(),
                'active30' => LicenseActivation::where('last_seen_at', '>=', now()->subDays(30))->count(),
            ],
        ]);
    }

    public function show(IssuedLicense $license): View
    {
        return view('admin.license.server-license', [
            'license' => $license->load('activations'),
            'modules' => MarketModule::where('is_paid', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $license = IssuedLicense::issue($this->validated($request));

        return redirect()->route('admin.license-server.show', $license)->with('status', "Lizenz {$license->key} wurde erstellt.");
    }

    public function update(Request $request, IssuedLicense $license): RedirectResponse
    {
        $license->update($this->validated($request) + ['status' => $request->input('status') === 'revoked' ? 'revoked' : 'active']);

        return back()->with('status', 'Lizenz gespeichert.');
    }

    public function releaseActivation(IssuedLicense $license, LicenseActivation $activation): RedirectResponse
    {
        abort_unless($activation->issued_license_id === $license->id, 404);
        $activation->delete();

        return back()->with('status', "Die Website {$activation->domain} wurde von der Lizenz gelöst.");
    }

    public function uploadModule(Request $request): RedirectResponse
    {
        $request->validate(['module' => ['required', 'file', 'mimes:zip', 'max:'.config('nova.plugin_max_kb')]],
            ['module.required' => 'Bitte wählen Sie die ZIP-Datei des Moduls.']);

        $zipPath = $request->file('module')->getRealPath();
        try {
            $manifest = $this->readManifest($zipPath);
        } catch (PluginException $e) {
            return back()->withErrors(['module' => $e->getMessage()]);
        }

        $file = $manifest->slug.'-'.$manifest->version.'.zip';
        File::ensureDirectoryExists(storage_path('app/marketplace'));
        copy($zipPath, storage_path('app/marketplace/'.$file));

        $module = MarketModule::firstOrNew(['slug' => $manifest->slug]);
        $isNew = ! $module->exists;
        $module->fill([
            'name' => $manifest->name,
            'description' => $module->description ?: $manifest->description,
            'version' => $manifest->version,
            'requires' => $manifest->requires,
            'is_paid' => $isNew ? $manifest->paid : $module->is_paid,
            'file' => $file,
            'sha256' => hash_file('sha256', storage_path('app/marketplace/'.$file)),
        ])->save();

        return back()->with('status', $isNew
            ? "„{$manifest->name}“ wurde hochgeladen. Prüfen Sie Preis und Beschreibung und veröffentlichen Sie es dann."
            : "„{$manifest->name}“ ist jetzt in Version {$manifest->version} verfügbar.");
    }

    public function updateModule(Request $request, MarketModule $module): RedirectResponse
    {
        $request->merge(['price_cents' => Money::parse($request->input('price'))]);
        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:500'],
            'category' => ['nullable', Rule::in(array_keys(MarketModule::CATEGORIES))],
            'price_cents' => ['nullable', 'integer', 'min:0'],
            'buy_url' => ['nullable', 'url:https', 'max:500'],
        ]);
        $module->update($data + ['is_paid' => $request->boolean('is_paid'), 'is_published' => $request->boolean('is_published')]);

        return back()->with('status', "„{$module->name}“ gespeichert.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:160'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'core' => ['boolean'],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', 'max:64'],
            'max_domains' => ['required', 'integer', 'min:1', 'max:1000'],
            'updates_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['core'] = $request->boolean('core');
        $data['modules'] = array_values(array_unique($data['modules'] ?? []));

        return $data;
    }

    private function readManifest(string $zipPath): PluginManifest
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new PluginException('Die Datei ist kein gültiges ZIP-Archiv.');
        }
        $json = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^([^/]+/)?plugin\.json$#', $name)) {
                $json = $zip->getFromIndex($i);
                break;
            }
        }
        $zip->close();
        $data = $json ? json_decode($json, true) : null;
        if (! is_array($data)) {
            throw new PluginException('Im Archiv fehlt eine gültige plugin.json.');
        }

        return PluginManifest::fromArray($data);
    }
}
