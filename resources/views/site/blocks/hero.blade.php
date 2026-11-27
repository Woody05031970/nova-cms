@php $tag = $first ? 'h1' : 'h2'; @endphp
<section @class(['relative isolate overflow-hidden', 'mt-8' => ! $first])>
    @if (! empty($d['image']))
        <img src="{{ $d['image'] }}" alt="" class="absolute inset-0 -z-10 size-full object-cover" @if($first) fetchpriority="high" @else loading="lazy" @endif>
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-slate-950/75 via-slate-950/45 to-slate-950/10"></div>
    @else
        <div class="absolute inset-0 -z-10 bg-gradient-to-br from-nova-700 via-nova-600 to-nova-500"></div>
    @endif
    <div class="mx-auto max-w-6xl px-4 py-24 text-white sm:px-6 sm:py-32">
        <div class="max-w-2xl">
            <{{ $tag }} class="text-4xl font-semibold tracking-tight sm:text-6xl">{{ $d['title'] ?? '' }}</{{ $tag }}>
            @if (! empty($d['text']))<p class="mt-5 text-lg text-white/90 sm:text-xl">{{ $d['text'] }}</p>@endif
            @if (! empty($d['button_label']) && ! empty($d['button_url']))
                <a href="{{ $d['button_url'] }}" class="mt-8 inline-flex rounded-lg bg-white px-5 py-3 font-semibold text-slate-900 shadow hover:bg-slate-100">{{ $d['button_label'] }}</a>
            @endif
        </div>
    </div>
</section>
