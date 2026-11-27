<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Setting;
use App\Support\Turbo\PageCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Throwable;

/** Modul „Turbo“: Zwischenspeicher ein- und ausschalten, leeren, Ladezeit messen */
class TurboController extends Controller
{
    public function index(): View
    {
        $big = Media::query()->where('kind', 'image')->where('size', '>', 500 * 1024)->orderByDesc('size')->limit(5)->get();

        return view('admin.turbo', [
            'enabled' => Setting::get('turbo_enabled', '1') === '1',
            'minutes' => PageCache::minutes(),
            'bigImages' => $big,
            'measure' => session('turbo_measure'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        if ($request->has('measure')) {
            return back()->with('turbo_measure', $this->measure());
        }
        $data = $request->validate(['turbo_minutes' => ['required', 'integer', 'min:5', 'max:1440']]);
        Setting::putMany(['turbo_enabled' => $request->boolean('turbo_enabled') ? '1' : '0', 'turbo_minutes' => (string) $data['turbo_minutes']]);

        return back()->with('status', $request->boolean('turbo_enabled') ? 'Turbo ist eingeschaltet.' : 'Turbo ist ausgeschaltet.');
    }

    public function clear(): RedirectResponse
    {
        PageCache::flush();

        return back()->with('status', 'Zwischenspeicher geleert. Die nächsten Aufrufe speichern die Seiten neu.');
    }

    /** Startseite zweimal als Besucher laden: ohne und mit Zwischenspeicher */
    private function measure(): ?array
    {
        try {
            $times = [];
            foreach ([1, 2] as $i) {
                $start = microtime(true);
                $r = Http::timeout(15)->withHeaders(['User-Agent' => 'Mozilla/5.0 NovaTurboCheck'])->get(url('/'));
                $times[] = ['ms' => (int) round((microtime(true) - $start) * 1000), 'cache' => $r->header('X-Nova-Cache') ?: '–'];
            }

            return $times;
        } catch (Throwable) {
            return null;
        }
    }
}
