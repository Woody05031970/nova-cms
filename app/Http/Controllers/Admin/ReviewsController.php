<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Reviews\GoogleReviews;
use App\Support\Security\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/** Modul „Google-Bewertungen“: Einrichtung, Vorschau und Bewertungslink mit QR-Code */
class ReviewsController extends Controller
{
    public function index(GoogleReviews $reviews): View
    {
        $url = GoogleReviews::writeUrl();

        return view('admin.reviews', [
            'placeId' => GoogleReviews::placeId(),
            'hasKey' => (bool) GoogleReviews::apiKey(),
            'data' => $reviews->data(),
            'writeUrl' => $url,
            'qr' => $url ? Totp::qrSvg($url) : null,
            'minRating' => (int) Setting::get('reviews_min_rating', '4'),
        ]);
    }

    public function update(Request $request, GoogleReviews $reviews): RedirectResponse
    {
        $data = $request->validate([
            'place_id' => ['required', 'string', 'max:300'],
            'api_key' => ['nullable', 'string', 'max:200'],
            'reviews_min_rating' => ['required', 'integer', 'min:1', 'max:5'],
        ], ['place_id.required' => 'Bitte geben Sie die Place-ID Ihres Unternehmens an.']);
        Setting::putMany(['google_place_id' => GoogleReviews::extractPlaceId($data['place_id']), 'reviews_min_rating' => (string) $data['reviews_min_rating']]);
        if (filled($data['api_key'] ?? null)) {
            GoogleReviews::saveKey($data['api_key']);
        }
        if (! GoogleReviews::apiKey()) {
            return back()->with('status', 'Gespeichert. Für Sterne und Bewertungen fehlt noch der API-Schlüssel; der Bewertungslink funktioniert schon.');
        }
        try {
            $d = $reviews->refresh();
        } catch (RuntimeException $e) {
            return back()->withErrors(['place_id' => $e->getMessage()]);
        }

        return back()->with('status', 'Verbunden: '.($d['name'] ?? 'Ihr Unternehmen').', '.number_format((float) $d['rating'], 1, ',', '').' Sterne aus '.$d['count'].' Bewertungen.');
    }

    public function refresh(GoogleReviews $reviews): RedirectResponse
    {
        try {
            $reviews->refresh();
        } catch (RuntimeException $e) {
            return back()->withErrors(['place_id' => $e->getMessage()]);
        }

        return back()->with('status', 'Bewertungen neu abgerufen.');
    }
}
