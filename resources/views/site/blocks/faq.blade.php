@php $items = $d['items'] ?? []; @endphp
<section class="mx-auto mt-20 max-w-3xl px-4 sm:px-6">
    @include('site.blocks.partials.heading')
    <div class="divide-y divide-slate-200 rounded-2xl border border-slate-200 bg-white">
        @foreach ($items as $i)
            <details class="group px-5 py-1 sm:px-6" @if($loop->first) open @endif>
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 py-4 font-medium text-slate-900">
                    <span>{{ $i['q'] }}</span>
                    <span class="grid size-7 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-500 transition group-open:rotate-45 group-open:bg-nova-600 group-open:text-white"><x-icon name="plus" class="size-4" /></span>
                </summary>
                <div class="pb-5 whitespace-pre-line text-slate-600">{{ $i['a'] }}</div>
            </details>
        @endforeach
    </div>
    @if ($items)
        <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => collect($items)->map(fn ($i) => ['@type' => 'Question', 'name' => $i['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $i['a']]])->values()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @endif
</section>
