@php $sent = session('contact_sent') === ($blockId ?? 'x'); @endphp
<section class="mx-auto mt-20 max-w-3xl px-4 sm:px-6" id="kontaktformular">
    @include('site.blocks.partials.heading')
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        @if ($sent)
            <div class="flex items-start gap-3 rounded-2xl bg-emerald-50 p-5 text-emerald-900" role="status">
                <x-icon name="check" class="mt-0.5 size-5 shrink-0" />
                <p>{{ ($d['success'] ?? '') ?: 'Vielen Dank! Ihre Nachricht ist angekommen. Wir melden uns so schnell wie möglich.' }}</p>
            </div>
        @else
            <form method="POST" action="{{ route('contact.send') }}#kontaktformular" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <input type="hidden" name="block" value="{{ $blockId ?? '' }}">
                <input type="hidden" name="page" value="{{ $pageId ?? '' }}">
                <input type="hidden" name="_t" value="{{ encrypt(now()->getTimestamp()) }}">
                <div class="hidden" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
                @if ($errors->has('contact'))<p class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 sm:col-span-2">{{ $errors->first('contact') }}</p>@endif
                <div>
                    <label class="nova-label" for="c-name">Ihr Name</label>
                    <input class="nova-input" id="c-name" name="name" required maxlength="120" autocomplete="name" value="{{ old('name') }}">
                </div>
                <div>
                    <label class="nova-label" for="c-email">E-Mail-Adresse</label>
                    <input class="nova-input" id="c-email" name="email" type="email" required maxlength="255" autocomplete="email" value="{{ old('email') }}">
                </div>
                @if (! empty($d['subjects']))
                    <div class="sm:col-span-2">
                        <label class="nova-label" for="c-subject">Worum geht es?</label>
                        <select class="nova-input" id="c-subject" name="subject">
                            @foreach ($d['subjects'] as $subj)<option @selected(old('subject') === $subj)>{{ $subj }}</option>@endforeach
                        </select>
                    </div>
                @endif
                <div class="sm:col-span-2">
                    <label class="nova-label" for="c-message">Ihre Nachricht</label>
                    <textarea class="nova-input" id="c-message" name="message" rows="5" required maxlength="5000">{{ old('message') }}</textarea>
                    @error('message')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-start gap-2 text-sm text-slate-600 sm:col-span-2">
                    <input type="checkbox" name="consent" value="1" required class="mt-1 accent-nova-600">
                    <span>Ich bin einverstanden, dass meine Angaben zur Beantwortung meiner Anfrage gespeichert werden. @if ($privacy = \App\Models\Page::published()->where('purpose', 'privacy')->first())Mehr dazu in der <a class="underline" href="{{ $privacy->url() }}">Datenschutzerklärung</a>.@endif</span>
                </label>
                <div class="sm:col-span-2">
                    <button class="inline-flex items-center gap-2 rounded-xl bg-nova-600 px-5 py-3 font-semibold text-white shadow-sm hover:bg-nova-700">{{ $d['button_label'] ?? 'Nachricht senden' }} <x-icon name="arrow" class="size-4" /></button>
                </div>
            </form>
        @endif
    </div>
</section>
