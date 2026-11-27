@if (\App\Support\Reviews\GoogleReviews::on() && ($g = app(\App\Support\Reviews\GoogleReviews::class)->data()))
@php
    $min = (int) \App\Models\Setting::get('reviews_min_rating', '4');
    $list = collect($g['reviews'])->filter(fn ($r) => $r['rating'] >= $min && trim($r['text']) !== '')->take($d['limit'] ?? 3);
    $write = \App\Support\Reviews\GoogleReviews::writeUrl();
    $stars = fn ($n) => str_repeat('★', (int) round($n)).str_repeat('☆', 5 - (int) round($n));
@endphp
<section class="mx-auto mt-20 max-w-6xl px-4 sm:px-6" data-reviews-block>
    @include('site.blocks.partials.heading')
    <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-center">
        <span class="text-4xl font-semibold">{{ number_format((float) $g['rating'], 1, ',', '') }}</span>
        <span class="text-2xl text-amber-500" aria-label="{{ number_format((float) $g['rating'], 1, ',', '') }} von 5 Sternen"><span aria-hidden="true">{{ $stars($g['rating']) }}</span></span>
        <a href="{{ $g['url'] }}" target="_blank" rel="noopener" class="text-slate-600 underline-offset-4 hover:underline">{{ $g['count'] }} Bewertungen bei Google</a>
    </div>
    @if ($list->isNotEmpty())
        <ul @class(['mt-8 grid gap-4', 'mx-auto max-w-xl' => $list->count() === 1, 'md:grid-cols-2' => $list->count() === 2 || $list->count() === 4, 'md:grid-cols-3' => $list->count() === 3 || $list->count() === 5])>
            @foreach ($list as $r)
                <li class="flex flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-amber-500" aria-label="{{ $r['rating'] }} von 5 Sternen"><span aria-hidden="true">{{ $stars($r['rating']) }}</span></p>
                    <blockquote class="mt-3 flex-1 text-slate-700"><p class="line-clamp-6">{{ $r['text'] }}</p></blockquote>
                    <p class="mt-4 flex items-center gap-3 text-sm">
                        @if ($r['photo'])<img src="{{ $r['photo'] }}" alt="" class="size-8 rounded-full" loading="lazy" referrerpolicy="no-referrer">@endif
                        <span>@if($r['author_url'])<a href="{{ $r['author_url'] }}" target="_blank" rel="noopener" class="font-medium hover:underline">{{ $r['author'] }}</a>@else<span class="font-medium">{{ $r['author'] }}</span>@endif<span class="block text-slate-500">{{ $r['time'] }}</span></span>
                    </p>
                </li>
            @endforeach
        </ul>
    @endif
    <p class="mt-6 flex flex-wrap items-center justify-center gap-4 text-sm text-slate-500">
        <span>Bewertungen von Google</span>
        @if (($d['show_button'] ?? true) && $write)<a href="{{ $write }}" target="_blank" rel="noopener" class="rounded-xl border border-slate-300 px-4 py-2 font-medium text-slate-800 hover:bg-slate-50">Bewertung schreiben</a>@endif
    </p>
</section>
@endif
