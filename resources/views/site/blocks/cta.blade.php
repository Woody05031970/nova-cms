@php $dark = ($d['style'] ?? 'dark') === 'dark'; @endphp
<section class="mx-auto mt-20 max-w-6xl px-4 sm:px-6">
    <div @class(['relative isolate overflow-hidden rounded-3xl px-6 py-14 text-center sm:px-12', 'nova-tech-bg text-white' => $dark, 'border border-nova-100 bg-nova-50 text-slate-900' => ! $dark])>
        @if (! empty($d['title']))<h2 class="mx-auto max-w-2xl text-3xl font-semibold tracking-tight text-balance sm:text-4xl">{{ $d['title'] }}</h2>@endif
        @if (! empty($d['text']))<p @class(['mx-auto mt-4 max-w-xl text-lg', 'text-white/75' => $dark, 'text-slate-600' => ! $dark])>{{ $d['text'] }}</p>@endif
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            @if (! empty($d['button_label']) && ! empty($d['button_url']))
                <a href="{{ $d['button_url'] }}" @class(['inline-flex items-center gap-2 rounded-xl px-5 py-3 font-semibold shadow-sm', 'bg-white text-slate-900 hover:bg-slate-100' => $dark, 'bg-nova-600 text-white hover:bg-nova-700' => ! $dark])>{{ $d['button_label'] }} <x-icon name="arrow" class="size-4" /></a>
            @endif
            @if (! empty($d['button2_label']) && ! empty($d['button2_url']))
                <a href="{{ $d['button2_url'] }}" @class(['inline-flex rounded-xl px-5 py-3 font-semibold', 'text-white ring-1 ring-white/25 hover:bg-white/10' => $dark, 'text-slate-800 ring-1 ring-slate-300 hover:bg-white' => ! $dark])>{{ $d['button2_label'] }}</a>
            @endif
        </div>
    </div>
</section>
