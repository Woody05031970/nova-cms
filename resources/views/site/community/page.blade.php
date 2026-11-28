@extends('site.community._layout')
@section('ctitle', $profile->page_title ?: $profile->user->name)
@section('main')
    @php $T = \App\Support\Community\Text::class; @endphp
    @if ($preview)<p class="mb-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">Vorschau: Diese Seite ist noch privat und nur für Sie sichtbar. <a href="{{ route('community.page.edit') }}" class="font-medium underline">Bearbeiten und veröffentlichen</a></p>@endif
    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" data-member-page>
        <header @class(['relative bg-gradient-to-r px-6 py-12 text-white sm:px-10 sm:py-16', $profile->colorClass()])>
            @if ($profile->cover)<img src="{{ $profile->cover }}" alt="" class="absolute inset-0 size-full object-cover opacity-30">@endif
            <div class="relative flex items-center gap-4">
                <span class="rounded-full bg-white/20 p-1">@include('site.community._avatar', ['p' => $profile, 'size' => 'size-16 text-xl'])</span>
                <div>
                    <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">{{ $profile->page_title ?: $profile->user->name }}</h1>
                    <a href="{{ $profile->url() }}" class="text-sm text-white/85 hover:text-white">von {{ $profile->user->name }} · {{ '@'.$profile->handle }}</a>
                </div>
            </div>
        </header>
        <div class="space-y-10 px-6 py-10 sm:px-10">
            @forelse ($profile->page_sections ?? [] as $s)
                @switch($s['type'])
                    @case('text')
                        <section>@if (! empty($s['heading']))<h2 class="text-xl font-semibold tracking-tight">{{ $s['heading'] }}</h2>@endif<div class="mt-2 leading-relaxed text-slate-700">{{ $T::render($s['text'] ?? '') }}</div></section>
                        @break
                    @case('quote')
                        <figure class="border-l-4 border-fuchsia-500 pl-5"><blockquote class="text-xl leading-relaxed text-slate-800 italic">„{{ $s['text'] ?? '' }}“</blockquote>@if (! empty($s['caption']))<figcaption class="mt-2 text-sm text-slate-500">– {{ $s['caption'] }}</figcaption>@endif</figure>
                        @break
                    @case('image')
                        @if (! empty($s['image']))<figure><img src="{{ $s['image'] }}" alt="{{ $s['caption'] ?? '' }}" class="w-full rounded-xl" loading="lazy">@if (! empty($s['caption']))<figcaption class="mt-2 text-center text-sm text-slate-500">{{ $s['caption'] }}</figcaption>@endif</figure>@endif
                        @break
                    @case('video')
                        @if (! empty($s['video_id']))<div class="-mx-4 sm:-mx-6">@include('site.blocks.video', ['d' => ['provider' => $s['provider'], 'video_id' => $s['video_id'], 'caption' => $s['caption'] ?? null]])</div>@endif
                        @break
                    @case('links')
                        <section>@if (! empty($s['heading']))<h2 class="text-xl font-semibold tracking-tight">{{ $s['heading'] }}</h2>@endif
                            <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                                @foreach (array_filter(explode("\n", $s['text'] ?? '')) as $line)
                                    @php [$label, $url] = array_map('trim', explode('|', $line, 2) + [1 => '']); @endphp
                                    <li><a href="{{ $url }}" rel="nofollow ugc noopener" target="_blank" class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3 font-medium hover:border-nova-300 hover:bg-nova-50/40">{{ $label }} <x-icon name="external" class="size-4 text-slate-400" /></a></li>
                                @endforeach
                            </ul>
                        </section>
                        @break
                @endswitch
            @empty
                <p class="text-center text-slate-500">Diese Seite ist noch leer.</p>
            @endforelse
        </div>
        <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 px-6 py-4 text-sm sm:px-10">
            <a href="{{ $profile->url() }}" class="text-nova-700 hover:underline">Zum Profil von {{ $profile->user->name }}</a>
            @include('site.community._report', ['type' => 'profile', 'id' => $profile->id])
        </footer>
    </article>
@endsection
