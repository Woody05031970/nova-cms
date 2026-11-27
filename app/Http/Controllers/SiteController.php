<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function home(): View
    {
        $page = Page::published()->where('is_home', true)->first();

        return $page
            ? view('site.page', ['page' => $page, 'preview' => false])
            : view('site.home', ['tagline' => Setting::get('tagline')]);
    }

    public function show(string $slug): View|RedirectResponse
    {
        $page = Page::published()->where('slug', $slug)->firstOrFail();

        if ($page->is_home) {
            return redirect()->route('home', status: 301);
        }

        return view('site.page', ['page' => $page, 'preview' => false]);
    }
}
