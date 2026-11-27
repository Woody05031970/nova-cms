@php $style = $d['style'] ?? 'grid'; $items = $d['items'] ?? []; @endphp
<section class="mx-auto mt-20 max-w-6xl px-4 sm:px-6">
    @include('site.blocks.partials.heading')
    @if ($style === 'stats')
        <dl class="grid gap-px overflow-hidden rounded-3xl border border-slate-200 bg-slate-200 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($items as $i)
                <div class="flex flex-col-reverse gap-1 bg-white p-6 sm:p-8">
                    <dt class="text-sm text-slate-600">{{ $i['text'] }}</dt>
                    <dd class="bg-gradient-to-br from-nova-700 to-fuchsia-600 bg-clip-text text-4xl font-semibold tracking-tight text-transparent">{{ $i['title'] }}</dd>
                </div>
            @endforeach
        </dl>
    @elseif ($style === 'steps')
        <ol @class(['grid gap-6', 'md:grid-cols-2' => count($items) <= 2 || count($items) === 4, 'md:grid-cols-3' => count($items) === 3 || count($items) > 4, 'lg:grid-cols-4' => count($items) === 4])>
            @foreach ($items as $i)
                <li class="relative rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <span class="grid size-10 place-items-center rounded-full bg-slate-900 font-mono text-sm font-semibold text-white">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    <h3 class="mt-4 text-lg font-semibold text-slate-900">{{ $i['title'] }}</h3>
                    <p class="mt-2 text-slate-600">{{ $i['text'] }}</p>
                </li>
            @endforeach
        </ol>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($items as $i)
                <div class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 transition hover:-translate-y-0.5 hover:border-nova-300 hover:shadow-lg hover:shadow-nova-600/5">
                    <span class="grid size-11 place-items-center rounded-xl bg-gradient-to-br from-nova-600 to-fuchsia-500 text-white shadow-sm shadow-nova-600/30"><x-icon :name="$i['icon'] ?? 'sparkles'" class="size-5" /></span>
                    <h3 class="mt-4 text-lg font-semibold text-slate-900">{{ $i['title'] }}</h3>
                    <p class="mt-2 text-slate-600">{{ $i['text'] }}</p>
                </div>
            @endforeach
        </div>
    @endif
</section>
