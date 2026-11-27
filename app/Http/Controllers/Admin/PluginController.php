<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plugin;
use App\Plugins\PluginException;
use App\Plugins\PluginManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PluginController extends Controller
{
    public function __construct(private PluginManager $plugins) {}

    public function index(): View
    {
        $this->plugins->sync();

        return view('admin.plugins', [
            'plugins' => Plugin::orderByDesc('enabled')->orderBy('name')->get(),
            'broken' => $this->plugins->discover()['errors'],
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'plugin' => ['required', 'file', 'mimes:zip', 'max:'.config('nova.plugin_max_kb')],
        ], [
            'plugin.required' => 'Bitte wählen Sie eine Plugin-Datei (.zip) aus.',
            'plugin.mimes' => 'Plugins werden als ZIP-Datei hochgeladen.',
            'plugin.max' => 'Die Datei ist zu groß.',
        ]);

        try {
            $manifest = $this->plugins->installFromZip($request->file('plugin')->getRealPath());
        } catch (PluginException $e) {
            return back()->withErrors(['plugin' => $e->getMessage()]);
        }

        return back()->with('status', "Das Plugin \"{$manifest->name}\" wurde installiert. Klicken Sie auf \"Aktivieren\", um es einzuschalten.");
    }

    public function activate(string $slug): RedirectResponse
    {
        return $this->run(fn () => $this->plugins->activate($slug), 'Plugin aktiviert.');
    }

    public function deactivate(string $slug): RedirectResponse
    {
        return $this->run(fn () => $this->plugins->deactivate($slug), 'Plugin deaktiviert.');
    }

    public function destroy(string $slug): RedirectResponse
    {
        return $this->run(fn () => $this->plugins->delete($slug), 'Plugin entfernt.');
    }

    private function run(callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (PluginException $e) {
            return back()->withErrors(['plugin' => $e->getMessage()]);
        }

        return redirect()->route('admin.plugins')->with('status', $message);
    }
}
