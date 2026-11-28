@extends('site.community._layout')
@section('ctitle', 'Meine Seite gestalten')
@section('cnoindex', '1')
@section('main')
    @php
        $sections = $profile->page_sections ?? [];
        $types = \App\Http\Controllers\Community\ProfileController::SECTIONS;
        $hint = ['text' => 'Überschrift und Text', 'image' => 'Foto mit Bildunterschrift', 'video' => 'YouTube- oder Vimeo-Video', 'links' => 'Knöpfe zu Ihren Links', 'quote' => 'Hervorgehobenes Zitat'];
    @endphp
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Meine Seite gestalten</h1>
            <p class="text-sm text-slate-600">Ihre eigene Seite in der Community: über Sie, Ihr Verein, Ihr Projekt oder Ihr Hobby.</p>
        </div>
        <a href="{{ route('community.page', $profile) }}" class="nova-btn-secondary py-2 text-sm" target="_blank">Ansehen</a>
    </div>
    <p @class(['mt-4 rounded-xl px-4 py-3 text-sm', 'bg-emerald-50 text-emerald-900' => $profile->page_published, 'bg-slate-100 text-slate-700' => ! $profile->page_published])>
        {{ $profile->page_published ? 'Ihre Seite ist veröffentlicht. Alle können sie über Ihr Profil sehen.' : 'Ihre Seite ist noch privat. Nur Sie sehen sie.' }}
    </p>

    <form method="POST" action="{{ route('community.page.update') }}" enctype="multipart/form-data" class="mt-5 space-y-4" data-page-editor>
        @csrf @method('PUT')
        {{-- Standard beim Drücken der Eingabetaste: speichern --}}
        <button name="op" value="save" class="hidden" tabindex="-1" aria-hidden="true"></button>
        <div class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-[1fr_auto]">
            <div><label class="nova-label" for="page_title">Titel der Seite</label><input class="nova-input" id="page_title" name="page_title" value="{{ old('page_title', $profile->page_title) }}" maxlength="120" placeholder="{{ $profile->user->name }}"></div>
            <fieldset><legend class="nova-label">Farbe</legend>
                <div class="flex gap-1.5">
                    @foreach (\App\Models\CommunityProfile::COLORS as $key => [$label, $class])
                        <label class="cursor-pointer" title="{{ $label }}"><input type="radio" name="page_color" value="{{ $key }}" class="peer sr-only" @checked(($profile->page_color ?: 'petrol') === $key)><span class="block size-9 rounded-full bg-gradient-to-br {{ $class }} ring-offset-2 peer-checked:ring-2 peer-checked:ring-slate-900 peer-focus-visible:ring-2 peer-focus-visible:ring-nova-500"></span><span class="sr-only">{{ $label }}</span></label>
                    @endforeach
                </div>
            </fieldset>
        </div>

        @foreach ($sections as $i => $s)
            <fieldset id="abschnitt-{{ $i }}" class="scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" data-section="{{ $s['type'] }}">
                <div class="flex items-center justify-between gap-2">
                    <legend class="font-semibold">{{ $i + 1 }}. {{ $types[$s['type']] ?? $s['type'] }}</legend>
                    <div class="flex gap-1">
                        <button name="op" value="up:{{ $i }}" class="rounded-lg px-2 py-1 text-sm text-slate-600 hover:bg-slate-100 disabled:opacity-30" @disabled($i === 0) aria-label="Nach oben">↑</button>
                        <button name="op" value="down:{{ $i }}" class="rounded-lg px-2 py-1 text-sm text-slate-600 hover:bg-slate-100 disabled:opacity-30" @disabled($i === count($sections) - 1) aria-label="Nach unten">↓</button>
                        <button name="op" value="remove:{{ $i }}" class="rounded-lg px-2 py-1 text-sm text-red-700 hover:bg-red-50" aria-label="Abschnitt entfernen">Entfernen</button>
                    </div>
                </div>
                <input type="hidden" name="sections[{{ $i }}][type]" value="{{ $s['type'] }}">
                <div class="mt-3 space-y-3">
                    @if (in_array($s['type'], ['text', 'links'], true))
                        <div><label class="nova-label" for="s{{ $i }}h">Überschrift</label><input class="nova-input" id="s{{ $i }}h" name="sections[{{ $i }}][heading]" value="{{ $s['heading'] ?? '' }}" maxlength="160"></div>
                    @endif
                    @if ($s['type'] === 'text')
                        <div><label class="nova-label" for="s{{ $i }}t">Text</label><textarea class="nova-input" id="s{{ $i }}t" name="sections[{{ $i }}][text]" rows="6" maxlength="6000">{{ $s['text'] ?? '' }}</textarea><p class="mt-1 text-xs text-slate-500">Links werden automatisch klickbar, **fett** hebt hervor.</p></div>
                    @elseif ($s['type'] === 'quote')
                        <div><label class="nova-label" for="s{{ $i }}t">Zitat</label><textarea class="nova-input" id="s{{ $i }}t" name="sections[{{ $i }}][text]" rows="3" maxlength="1000">{{ $s['text'] ?? '' }}</textarea></div>
                        <div><label class="nova-label" for="s{{ $i }}c">Von wem?</label><input class="nova-input" id="s{{ $i }}c" name="sections[{{ $i }}][caption]" value="{{ $s['caption'] ?? '' }}" maxlength="200"></div>
                    @elseif ($s['type'] === 'image')
                        <input type="hidden" name="sections[{{ $i }}][image]" value="{{ $s['image'] ?? '' }}">
                        @if (! empty($s['image']))<img src="{{ $s['image'] }}" alt="" class="max-h-56 rounded-xl">@endif
                        <div><label class="nova-label" for="s{{ $i }}u">{{ empty($s['image']) ? 'Bild hochladen' : 'Anderes Bild hochladen' }}</label><input type="file" id="s{{ $i }}u" name="sections[{{ $i }}][upload]" accept="image/png,image/jpeg,image/webp,image/gif" class="text-sm"></div>
                        <div><label class="nova-label" for="s{{ $i }}c">Bildunterschrift</label><input class="nova-input" id="s{{ $i }}c" name="sections[{{ $i }}][caption]" value="{{ $s['caption'] ?? '' }}" maxlength="200"></div>
                    @elseif ($s['type'] === 'video')
                        <div><label class="nova-label" for="s{{ $i }}v">Adresse des Videos</label><input class="nova-input" id="s{{ $i }}v" name="sections[{{ $i }}][url]" value="{{ $s['url'] ?? '' }}" placeholder="https://www.youtube.com/watch?v=…" maxlength="255">
                            @if (! empty($s['url']) && empty($s['video_id']))<p class="mt-1 text-sm text-amber-800">Diese Adresse erkennen wir nicht. Bitte nehmen Sie einen Link von YouTube oder Vimeo.</p>@endif</div>
                        <div><label class="nova-label" for="s{{ $i }}c">Beschreibung</label><input class="nova-input" id="s{{ $i }}c" name="sections[{{ $i }}][caption]" value="{{ $s['caption'] ?? '' }}" maxlength="200"></div>
                    @elseif ($s['type'] === 'links')
                        <div><label class="nova-label" for="s{{ $i }}t">Links</label><textarea class="nova-input font-mono text-sm" id="s{{ $i }}t" name="sections[{{ $i }}][text]" rows="4" maxlength="3000" placeholder="Mein Verein | https://www.beispiel.de&#10;Instagram | https://instagram.com/…">{{ $s['text'] ?? '' }}</textarea><p class="mt-1 text-xs text-slate-500">Eine Zeile pro Link: Beschriftung | Adresse</p></div>
                    @endif
                </div>
            </fieldset>
        @endforeach

        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-5">
            <p class="font-medium">Abschnitt hinzufügen</p>
            <div class="mt-3 grid gap-2 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($types as $key => $label)
                    <button name="op" value="add:{{ $key }}" class="rounded-xl border border-slate-200 px-3 py-2.5 text-left text-sm hover:border-nova-300 hover:bg-nova-50/40" data-add="{{ $key }}"><span class="block font-semibold">+ {{ $label }}</span><span class="text-xs text-slate-500">{{ $hint[$key] }}</span></button>
                @endforeach
            </div>
        </div>

        <div class="sticky bottom-0 z-10 -mx-4 flex flex-wrap justify-end gap-2 border-t border-slate-200 bg-white/90 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-2xl sm:border">
            <button name="op" value="save" class="nova-btn-secondary py-2">Speichern</button>
            @if ($profile->page_published)
                <button name="op" value="unpublish" class="nova-btn-secondary py-2">Wieder privat machen</button>
            @else
                <button name="op" value="publish" class="nova-btn py-2" data-publish>Speichern und veröffentlichen</button>
            @endif
        </div>
    </form>
@endsection
