<?php

namespace App\Http\Controllers\Admin;

use App\Ai\AiException;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Setting;
use App\Support\I18n\Languages;
use App\Support\I18n\PageTranslator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Modul „Mehrsprachigkeit“: Sprachen wählen, Übersicht der Übersetzungen, Übersetzung anlegen */
class LanguageController extends Controller
{
    public function index(): View
    {
        return view('admin.languages', [
            'active' => Languages::active(),
            'pages' => Page::main()->where('status', '!=', 'trash')->whereNull('purpose')->with('translations')->orderByDesc('is_home')->orderBy('menu_order')->orderBy('title')->get(),
            'legal' => Page::main()->whereNotNull('purpose')->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['languages' => ['nullable', 'array'], 'languages.*' => ['string', 'max:5']]);
        Setting::put('languages', json_encode(array_values(array_intersect(array_keys(Languages::AVAILABLE), $data['languages'] ?? []))));

        return redirect()->route('admin.languages')->with('status', 'Sprachen gespeichert.');
    }

    public function translate(Request $request, Page $page, PageTranslator $translator): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', Rule::in(Languages::active())]]);
        abort_if($page->locale !== null, 422);
        if ($existing = $page->translations()->where('locale', $data['locale'])->first()) {
            return redirect()->route('admin.pages.edit', $existing);
        }
        try {
            $translation = $translator->create($page, $data['locale'], $request->boolean('ai'));
        } catch (AiException $e) {
            $translation = $translator->create($page, $data['locale'], false);

            return redirect()->route('admin.pages.edit', $translation)
                ->with('status', 'Die KI konnte nicht übersetzen ('.$e->getMessage().'). Die Seite wurde als Kopie angelegt, bitte Texte selbst übersetzen.');
        }
        $name = Languages::name($data['locale'], false);

        return redirect()->route('admin.pages.edit', $translation)->with('status', $request->boolean('ai')
            ? $name.'e Fassung mit KI übersetzt. Bitte kurz gegenlesen und dann veröffentlichen.'
            : $name.'e Fassung als Kopie angelegt. Überschreiben Sie die Texte und veröffentlichen Sie die Seite.');
    }
}
