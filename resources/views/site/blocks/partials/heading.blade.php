@if (! empty($d['eyebrow']) || ! empty($d['title']) || ! empty($d['intro']))
    <div @class(['mx-auto mb-10 max-w-3xl', 'text-center' => $center ?? true])>
        @if (! empty($d['eyebrow']))<p class="font-mono text-xs font-semibold tracking-[0.18em] text-nova-600 uppercase">{{ $d['eyebrow'] }}</p>@endif
        @if (! empty($d['title']))<{{ ! empty($first) ? 'h1' : 'h2' }} @class(['mt-2 font-semibold tracking-tight text-balance text-slate-900', 'text-4xl sm:text-5xl' => ! empty($first), 'text-3xl sm:text-4xl' => empty($first)])>{{ $d['title'] }}</{{ ! empty($first) ? 'h1' : 'h2' }}>@endif
        @if (! empty($d['intro']))<p class="mt-4 text-lg text-pretty text-slate-600">{{ $d['intro'] }}</p>@endif
    </div>
@endif
