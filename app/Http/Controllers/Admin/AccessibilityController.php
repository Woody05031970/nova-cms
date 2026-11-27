<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\Accessibility\AccessibilityChecker;
use App\Support\Pages\PageTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Barrierefreiheits-Check nach WCAG 2.1 AA / BFSG */
class AccessibilityController extends Controller
{
    public function index(AccessibilityChecker $checker): View
    {
        return view('admin.accessibility', $checker->run() + [
            'statement' => Page::where('purpose', 'accessibility')->first(),
        ]);
    }

    /** Legt die Erklärung zur Barrierefreiheit an und veröffentlicht sie (Fußzeile verlinkt sie automatisch). */
    public function statement(Request $request): RedirectResponse
    {
        $page = Page::where('purpose', 'accessibility')->first();
        if (! $page) {
            $tpl = PageTemplates::make('barrierefreiheit');
            $page = Page::create([
                'title' => $tpl['title'],
                'slug' => Page::uniqueSlug('barrierefreiheit'),
                'blocks' => Page::cleanBlocks($tpl['blocks']),
                'purpose' => 'accessibility',
                'show_in_menu' => false,
                'status' => 'published',
                'published_at' => now(),
                'updated_by' => $request->user()->id,
            ]);
        } elseif (! $page->isPublished()) {
            $page->update(['status' => 'published', 'published_at' => $page->published_at ?? now()]);
        }

        return redirect()->route('admin.pages.edit', $page)
            ->with('status', 'Die Erklärung zur Barrierefreiheit ist veröffentlicht und in der Fußzeile verlinkt. Bitte ergänzen Sie den Abschnitt „Bekannte Einschränkungen“.');
    }
}
