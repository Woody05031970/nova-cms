<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Setting;
use App\Support\I18n\Languages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function home(): View|Response
    {
        $page = Page::published()->main()->where('is_home', true)->first();

        return $page
            ? $this->render($page)
            : view('site.home', ['tagline' => Setting::get('tagline')]);
    }

    public function show(string $slug): View|RedirectResponse|Response
    {
        $page = Page::published()->where('slug', $slug)->firstOrFail();

        if ($page->is_home || $page->locale !== null) {
            return redirect()->to($page->url(), 301);
        }

        return $this->render($page);
    }

    public function localeHome(string $locale): View|RedirectResponse|Response
    {
        if (! Languages::isActive($locale)) {
            return $this->show($locale);
        }
        $home = Page::published()->main()->where('is_home', true)->first();
        $page = $home ? Page::published()->where('translation_of', $home->id)->where('locale', $locale)->first() : null;
        if (! $page) {
            return redirect()->route('home');
        }

        return $this->render($page);
    }

    public function localeShow(string $locale, string $slug): View|RedirectResponse|Response
    {
        abort_unless(Languages::isActive($locale), 404);
        $page = Page::published()->where('locale', $locale)->where('slug', $slug)->firstOrFail();
        if ($page->source?->is_home) {
            return redirect()->to($page->url(), 301);
        }

        return $this->render($page);
    }

    private function render(Page $page): View|Response
    {
        Languages::setCurrent($page->locale);
        if ($page->isMembersOnly() && ! auth()->user()?->canSeeMembersArea()) {
            session()->put('members.intended', $page->url());

            return response()->view('site.members.locked', ['page' => $page], 403);
        }

        return view('site.page', ['page' => $page, 'preview' => false]);
    }
}
