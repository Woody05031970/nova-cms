<?php

namespace App\Http\Controllers\Admin\Licensing;

use App\Http\Controllers\Controller;
use App\Licensing\License;
use App\Licensing\Marketplace;
use App\Models\MarketModule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class MarketplaceController extends Controller
{
    public function index(Request $request, Marketplace $market, License $license): View
    {
        $modules = [];
        $error = null;
        try {
            $modules = $request->boolean('neu') ? ($market->modules(true) ? $market->overview() : []) : $market->overview();
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        return view('admin.license.marketplace', [
            'modules' => collect($modules)->sortBy([['installed', 'desc'], ['name', 'asc']])->values(),
            'error' => $error,
            'status' => $license->status(),
            'categories' => MarketModule::CATEGORIES,
        ]);
    }

    public function install(string $slug, Marketplace $market): RedirectResponse
    {
        try {
            $manifest = $market->install($slug);
        } catch (Throwable $e) {
            return back()->withErrors(['market' => $e->getMessage()]);
        }

        return redirect()->route('admin.plugins')->with('status', "„{$manifest->name}“ (Version {$manifest->version}) ist installiert. Klicken Sie auf „Aktivieren“, falls es noch aus ist.");
    }
}
