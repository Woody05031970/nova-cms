@if (\App\Licensing\Features::on('newsletter'))
@php $sent = session('newsletter_sent') === ($blockId ?? 'x'); $dark = ($d['style'] ?? 'dark') === 'dark'; @endphp
<section class="mx-auto mt-20 max-w-6xl px-4 sm:px-6" id="newsletter">
    <div @class(['relative isolate overflow-hidden rounded-3xl px-6 py-12 sm:px-12', 'nova-tech-bg text-white' => $dark, 'border border-nova-100 bg-nova-50' => ! $dark])>
        <div class="grid items-center gap-8 lg:grid-cols-2">
            <div>
                @if (! empty($d['eyebrow']))<p @class(['font-mono text-xs font-semibold tracking-[0.18em] uppercase', 'text-nova-300' => $dark, 'text-nova-600' => ! $dark])>{{ $d['eyebrow'] }}</p>@endif
                <h2 @class(['mt-2 text-3xl font-semibold tracking-tight text-balance', 'text-slate-900' => ! $dark])>{{ $d['title'] ?: 'Newsletter abonnieren' }}</h2>
                @if (! empty($d['intro']))<p @class(['mt-3 text-lg', 'text-white/75' => $dark, 'text-slate-600' => ! $dark])>{{ $d['intro'] }}</p>@endif
            </div>
            <div>
                @if ($sent)
                    <div @class(['flex items-start gap-3 rounded-2xl p-5', 'bg-white/10 ring-1 ring-white/20' => $dark, 'bg-white text-slate-800' => ! $dark]) role="status">
                        <x-icon name="mail" class="mt-0.5 size-5 shrink-0" />
                        <p><strong>Fast geschafft!</strong> Wir haben Ihnen eine E-Mail geschickt. Bitte klicken Sie auf den Link darin, um die Anmeldung zu bestätigen.</p>
                    </div>
                @else
                    <form method="POST" action="{{ route('newsletter.subscribe') }}#newsletter" class="space-y-3">
                        @csrf
                        <input type="hidden" name="block" value="{{ $blockId ?? '' }}">
                        <input type="hidden" name="_t" value="{{ encrypt(now()->getTimestamp()) }}">
                        <div class="hidden" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            @if (! empty($d['ask_name']))<input name="name" maxlength="120" autocomplete="given-name" placeholder="Vorname" aria-label="Vorname" class="min-w-0 rounded-xl border-0 bg-white px-4 py-3 text-slate-900 ring-1 ring-slate-200 sm:w-36" value="{{ old('name') }}">@endif
                            <input name="email" type="email" required maxlength="255" autocomplete="email" placeholder="Ihre E-Mail-Adresse" aria-label="E-Mail-Adresse" class="min-w-0 flex-1 rounded-xl border-0 bg-white px-4 py-3 text-slate-900 ring-1 ring-slate-200" value="{{ old('email') }}">
                            <button class="rounded-xl bg-nova-600 px-5 py-3 font-semibold text-white shadow-sm hover:bg-nova-700">{{ $d['button_label'] ?? 'Abonnieren' }}</button>
                        </div>
                        <label @class(['flex items-start gap-2 text-xs', 'text-white/70' => $dark, 'text-slate-600' => ! $dark])>
                            <input type="checkbox" name="consent" value="1" required class="mt-0.5 accent-nova-600">
                            <span>Ich möchte den Newsletter erhalten und kann mich jederzeit abmelden. @if ($privacy = \App\Models\Page::published()->where('purpose', 'privacy')->first())<a class="underline" href="{{ $privacy->url() }}">Datenschutz</a>@endif</span>
                        </label>
                        @if ($errors->has('email') || $errors->has('consent'))<p class="text-sm text-red-300">{{ $errors->first('email') ?: $errors->first('consent') }}</p>@endif
                    </form>
                @endif
            </div>
        </div>
    </div>
</section>
@endif
