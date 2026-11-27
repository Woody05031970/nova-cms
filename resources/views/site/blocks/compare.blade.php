@php
    $cell = function ($v, bool $ours) {
        return match ($v) {
            'yes' => '<span class="inline-flex items-center gap-1.5 '.($ours ? 'font-medium text-emerald-700' : 'text-slate-700').'"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5 10 17 19 7"/></svg>Ja</span>',
            'no' => '<span class="inline-flex items-center gap-1.5 text-slate-400"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M7 7l10 10M17 7 7 17"/></svg>Nein</span>',
            'partly' => '<span class="text-slate-500">teilweise</span>',
            default => e($v),
        };
    };
@endphp
<section class="mx-auto mt-20 max-w-5xl px-4 sm:px-6">
    @include('site.blocks.partials.heading')
    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
        <table class="w-full min-w-[560px] text-left text-sm sm:text-base">
            <thead>
                <tr class="border-b border-slate-200">
                    <th class="w-2/5 px-5 py-4 font-medium text-slate-500" scope="col"><span class="sr-only">Merkmal</span></th>
                    <th class="bg-nova-50/70 px-5 py-4 font-semibold text-nova-800" scope="col">{{ $d['ours'] ?: 'Nova CMS' }}</th>
                    <th class="px-5 py-4 font-semibold text-slate-700" scope="col">{{ $d['theirs'] }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($d['rows'] ?? [] as $r)
                    <tr>
                        <th class="px-5 py-3.5 font-medium text-slate-900" scope="row">{{ $r['label'] }}</th>
                        <td class="bg-nova-50/70 px-5 py-3.5 text-slate-800">{!! $cell($r['ours'], true) !!}</td>
                        <td class="px-5 py-3.5 text-slate-600">{!! $cell($r['theirs'], false) !!}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if (! empty($d['note']))<p class="mt-4 text-center text-xs text-slate-500">{{ $d['note'] }}</p>@endif
</section>
