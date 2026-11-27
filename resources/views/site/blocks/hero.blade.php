@php $tag = $first ? 'h1' : 'h2'; @endphp
@if (($d['variant'] ?? 'image') === 'tech')
<section @class(['nova-tech-bg relative isolate overflow-hidden text-white', 'mt-8' => ! $first])>
    <div class="mx-auto grid max-w-6xl items-center gap-12 px-4 py-20 sm:px-6 lg:grid-cols-[1.05fr_1fr] lg:py-28">
        <div>
            @if (! empty($d['eyebrow']))
                <p class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1 font-mono text-xs tracking-wide text-white/80"><span class="size-1.5 animate-pulse rounded-full bg-emerald-400"></span>{{ $d['eyebrow'] }}</p>
            @endif
            <{{ $tag }} class="mt-5 text-4xl font-semibold tracking-tight text-balance sm:text-5xl xl:text-[3.5rem] xl:leading-[1.05]">{{ $d['title'] ?? '' }}</{{ $tag }}>
            @if (! empty($d['text']))<p class="mt-5 max-w-xl text-lg text-pretty text-white/75 sm:text-xl">{{ $d['text'] }}</p>@endif
            <div class="mt-8 flex flex-wrap gap-3">
                @if (! empty($d['button_label']) && ! empty($d['button_url']))
                    <a href="{{ $d['button_url'] }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-5 py-3 font-semibold text-slate-900 shadow-lg shadow-black/20 hover:bg-slate-100">{{ $d['button_label'] }} <x-icon name="arrow" class="size-4" /></a>
                @endif
                @if (! empty($d['button2_label']) && ! empty($d['button2_url']))
                    <a href="{{ $d['button2_url'] }}" class="inline-flex rounded-xl px-5 py-3 font-semibold text-white ring-1 ring-white/25 hover:bg-white/10">{{ $d['button2_label'] }}</a>
                @endif
            </div>
        </div>
        @if (! empty($d['image']))
            <img src="{{ $d['image'] }}" alt="" class="w-full rounded-2xl shadow-2xl ring-1 ring-white/10" @if($first) fetchpriority="high" @endif>
        @else
            @include('site.blocks.partials.product-shot')
        @endif
    </div>
</section>
@else
<section @class(['relative isolate overflow-hidden', 'mt-8' => ! $first])>
    @if (! empty($d['image']))
        <img src="{{ $d['image'] }}" alt="" class="absolute inset-0 -z-10 size-full object-cover" @if($first) fetchpriority="high" @else loading="lazy" @endif>
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-slate-950/75 via-slate-950/45 to-slate-950/10"></div>
    @else
        <div class="absolute inset-0 -z-10 bg-gradient-to-br from-nova-700 via-nova-600 to-nova-500"></div>
    @endif
    <div class="mx-auto max-w-6xl px-4 py-24 text-white sm:px-6 sm:py-32">
        <div class="max-w-2xl">
            @if (! empty($d['eyebrow']))<p class="font-mono text-xs font-semibold tracking-[0.18em] text-white/80 uppercase">{{ $d['eyebrow'] }}</p>@endif
            <{{ $tag }} class="text-4xl font-semibold tracking-tight sm:text-6xl">{{ $d['title'] ?? '' }}</{{ $tag }}>
            @if (! empty($d['text']))<p class="mt-5 text-lg text-white/90 sm:text-xl">{{ $d['text'] }}</p>@endif
            <div class="mt-8 flex flex-wrap gap-3">
                @if (! empty($d['button_label']) && ! empty($d['button_url']))
                    <a href="{{ $d['button_url'] }}" class="inline-flex rounded-lg bg-white px-5 py-3 font-semibold text-slate-900 shadow hover:bg-slate-100">{{ $d['button_label'] }}</a>
                @endif
                @if (! empty($d['button2_label']) && ! empty($d['button2_url']))
                    <a href="{{ $d['button2_url'] }}" class="inline-flex rounded-lg px-5 py-3 font-semibold text-white ring-1 ring-white/40 hover:bg-white/10">{{ $d['button2_label'] }}</a>
                @endif
            </div>
        </div>
    </div>
</section>
@endif
