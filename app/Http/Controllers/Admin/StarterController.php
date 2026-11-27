<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Starter\NovaSalesSite;
use Illuminate\Http\RedirectResponse;

/** Spielt ein Startpaket (fertige Website) nachträglich ein. */
class StarterController extends Controller
{
    public function store(NovaSalesSite $starter): RedirectResponse
    {
        if (NovaSalesSite::installed()) {
            return back()->with('status', 'Die Verkaufswebsite ist bereits eingerichtet.');
        }
        $result = $starter->install();

        return redirect()->route('admin.pages.index')
            ->with('status', "Die Verkaufswebsite ist eingerichtet: {$result['pages']} Seiten und {$result['products']} Shop-Produkte. Passen Sie Preise und Texte jetzt nach Ihren Wünschen an.");
    }

    /** Ergänzt neue Produkte und Module einer neueren Version, ohne eigene Texte zu überschreiben. */
    public function update(NovaSalesSite $starter): RedirectResponse
    {
        if (! NovaSalesSite::installed()) {
            return back()->with('status', 'Die Verkaufswebsite ist noch nicht eingerichtet.');
        }
        $result = $starter->refresh();

        return back()->with('status', "Die Verkaufswebsite ist auf dem neuesten Stand: {$result['products']} Produkte ergänzt, {$result['pages']} Seiten mit dem Modul-Baukasten aktualisiert.");
    }
}
