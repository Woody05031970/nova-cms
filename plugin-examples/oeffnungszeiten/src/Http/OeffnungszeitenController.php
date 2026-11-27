<?php

namespace NovaPlugins\Oeffnungszeiten\Http;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OeffnungszeitenController extends Controller
{
    public const TAGE = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];

    /** @return array<int, array{tag: string, von: ?string, bis: ?string, geschlossen: bool}> */
    public static function load(): array
    {
        $rows = DB::table('oeffnungszeiten')->get()->keyBy('wochentag');

        return collect(self::TAGE)->map(fn ($tag, $i) => [
            'tag' => $tag,
            'von' => $rows[$i]->von ?? null,
            'bis' => $rows[$i]->bis ?? null,
            'geschlossen' => (bool) ($rows[$i]->geschlossen ?? true),
        ])->all();
    }

    public function edit(): View
    {
        return view('oeffnungszeiten::edit', ['tage' => self::load()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'tage' => ['array'],
            'tage.*.von' => ['nullable', 'date_format:H:i'],
            'tage.*.bis' => ['nullable', 'date_format:H:i'],
        ], ['date_format' => 'Bitte Uhrzeiten im Format 08:30 angeben.']);

        foreach (self::TAGE as $i => $tag) {
            $input = $request->input("tage.$i", []);
            $offen = ! empty($input['offen']) && ! empty($input['von']) && ! empty($input['bis']);

            DB::table('oeffnungszeiten')->updateOrInsert(['wochentag' => $i], [
                'von' => $offen ? $input['von'] : null,
                'bis' => $offen ? $input['bis'] : null,
                'geschlossen' => ! $offen,
            ]);
        }

        return back()->with('status', 'Öffnungszeiten gespeichert.');
    }
}
