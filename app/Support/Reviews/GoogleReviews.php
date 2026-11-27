<?php

namespace App\Support\Reviews;

use App\Licensing\Features;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Modul „Google-Bewertungen“: Sterne, Anzahl und die neuesten Bewertungen aus Google (Places API, eigener API-Schlüssel)
 * sowie ein Link mit QR-Code, über den Kunden direkt eine Bewertung schreiben.
 * Google erlaubt nur kurzes Zwischenspeichern; deshalb werden die Daten höchstens 12 Stunden gehalten.
 */
class GoogleReviews
{
    private const CACHE = 'nova_google_reviews';

    private const ENDPOINT = 'https://places.googleapis.com/v1/places/';

    public static function on(): bool
    {
        return Features::on('google-bewertungen');
    }

    public static function placeId(): ?string
    {
        return Setting::get('google_place_id') ?: null;
    }

    public static function apiKey(): ?string
    {
        $stored = Setting::get('google_places_key');
        if (! $stored) {
            return null;
        }
        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            return null;
        }
    }

    public static function saveKey(?string $key): void
    {
        Setting::put('google_places_key', $key ? Crypt::encryptString(trim($key)) : null);
        Cache::forget(self::CACHE);
    }

    /** Link, über den Kunden eine Bewertung schreiben */
    public static function writeUrl(): ?string
    {
        $id = self::placeId();

        return $id ? 'https://search.google.com/local/writereview?placeid='.rawurlencode($id) : null;
    }

    /**
     * @return array{name: ?string, rating: ?float, count: int, url: ?string, reviews: list<array{author: string, author_url: ?string, photo: ?string, rating: int, text: string, time: string}>}|null
     */
    public function data(): ?array
    {
        if (! self::on() || ! self::placeId() || ! self::apiKey()) {
            return null;
        }
        try {
            return Cache::remember(self::CACHE, now()->addHours(12), fn () => $this->fetch());
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    public function refresh(): array
    {
        Cache::forget(self::CACHE);
        $data = $this->fetch();
        Cache::put(self::CACHE, $data, now()->addHours(12));

        return $data;
    }

    public function fetch(): array
    {
        $res = Http::timeout(10)->withHeaders([
            'X-Goog-Api-Key' => (string) self::apiKey(),
            'X-Goog-FieldMask' => 'displayName,rating,userRatingCount,googleMapsUri,reviews',
        ])->get(self::ENDPOINT.rawurlencode((string) self::placeId()), ['languageCode' => 'de']);
        if (! $res->successful()) {
            $msg = $res->json('error.message') ?: 'HTTP '.$res->status();
            throw new RuntimeException('Google meldet: '.$msg);
        }
        $j = $res->json();

        return [
            'name' => $j['displayName']['text'] ?? null,
            'rating' => isset($j['rating']) ? round((float) $j['rating'], 1) : null,
            'count' => (int) ($j['userRatingCount'] ?? 0),
            'url' => $j['googleMapsUri'] ?? null,
            'reviews' => collect($j['reviews'] ?? [])->map(fn ($r) => [
                'author' => (string) ($r['authorAttribution']['displayName'] ?? 'Google-Nutzer'),
                'author_url' => $r['authorAttribution']['uri'] ?? null,
                'photo' => $r['authorAttribution']['photoUri'] ?? null,
                'rating' => (int) ($r['rating'] ?? 0),
                'text' => (string) ($r['text']['text'] ?? $r['originalText']['text'] ?? ''),
                'time' => (string) ($r['relativePublishTimeDescription'] ?? ''),
            ])->values()->all(),
        ];
    }

    /** Google-Maps-Link oder Place-ID: Place-ID heraussuchen, soweit sie im Text steht */
    public static function extractPlaceId(string $input): string
    {
        $input = trim($input);
        if (preg_match('/(?:placeid=|place_id[=:])([A-Za-z0-9_-]{20,})/', $input, $m)) {
            return $m[1];
        }

        return $input;
    }
}
