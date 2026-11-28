@extends('site.community._layout')
@section('ctitle', $tag !== '' ? '#'.$tag : \App\Support\Community\Community::title())
@section('main')
    @php $me = \App\Support\Community\Community::me(); @endphp
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ $tag !== '' ? '#'.$tag : 'Pinnwand' }}</h1>
            <p class="text-sm text-slate-600">{{ $tag !== '' ? 'Alle Beiträge mit diesem Schlagwort.' : 'Neuigkeiten, Fragen und Fotos aus der '.\App\Support\Community\Community::title().'.' }}</p>
        </div>
        @if ($me)
            <div class="flex rounded-xl bg-white p-1 text-sm ring-1 ring-slate-200">
                <a href="{{ route('community.home') }}" @class(['rounded-lg px-3 py-1.5 font-medium', 'bg-nova-600 text-white' => ! $following && $tag === '', 'text-slate-600 hover:text-slate-900' => $following || $tag !== ''])>Alle</a>
                <a href="{{ route('community.home', ['ansicht' => 'folge']) }}" @class(['rounded-lg px-3 py-1.5 font-medium', 'bg-nova-600 text-white' => $following, 'text-slate-600 hover:text-slate-900' => ! $following])>Denen ich folge</a>
            </div>
        @endif
    </div>

    @if ($me && $tag === '')
        <form method="POST" action="{{ route('community.posts.store') }}" enctype="multipart/form-data" class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" data-composer>
            @csrf
            <div class="flex gap-3">
                @include('site.community._avatar', ['p' => \App\Support\Community\Community::profile($me)])
                <div class="min-w-0 flex-1">
                    <label for="body" class="sr-only">Neuer Beitrag</label>
                    <textarea id="body" name="body" rows="3" maxlength="5000" required class="nova-input resize-y text-[15px]" placeholder="Was gibt es Neues, {{ strtok($me->name, ' ') }}?">{{ old('body') }}</textarea>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                        <label class="flex min-w-0 cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm whitespace-nowrap text-slate-600 hover:bg-slate-100">
                            <x-icon name="image" class="size-4.5 text-nova-600" /> Foto hinzufügen
                            <input type="file" name="image" accept="image/png,image/jpeg,image/webp,image/gif" class="w-28 min-w-0 truncate text-xs file:hidden sm:w-40">
                        </label>
                        <p class="hidden text-xs text-slate-500 sm:block">Tipp: @kurzname erwähnt, #thema verschlagwortet, **fett** hebt hervor</p>
                        <button class="nova-btn py-2" data-post-submit>Posten</button>
                    </div>
                </div>
            </div>
        </form>
    @endif

    <div class="mt-5 space-y-4">
        @forelse ($posts as $post)
            @include('site.community._post', ['post' => $post])
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
                <p class="font-medium">Noch keine Beiträge.</p>
                <p class="mt-1 text-sm text-slate-600">{{ $me ? 'Schreiben Sie den ersten!' : 'Melden Sie sich an und schreiben Sie den ersten.' }}</p>
            </div>
        @endforelse
    </div>
    <div class="mt-6">{{ $posts->links() }}</div>
@endsection
@section('aside')
    <section class="rounded-2xl border border-slate-200 bg-white p-4">
        <h2 class="text-sm font-semibold">Neu im Forum</h2>
        <ul class="mt-2 space-y-2 text-sm">
            @forelse ($topics as $t)
                <li><a href="{{ $t->url() }}" class="font-medium text-slate-800 hover:text-nova-700">{{ $t->title }}</a><span class="block text-xs text-slate-500">{{ $t->board->name }} · {{ $t->replies_count }} {{ $t->replies_count === 1 ? 'Antwort' : 'Antworten' }}</span></li>
            @empty
                <li class="text-slate-500">Noch keine Themen.</li>
            @endforelse
        </ul>
        <a href="{{ route('community.forums') }}" class="mt-3 inline-block text-sm font-medium text-nova-700 hover:underline">Zu den Foren</a>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-4">
        <h2 class="text-sm font-semibold">Neue Mitglieder</h2>
        <ul class="mt-3 grid grid-cols-4 gap-2">
            @foreach ($newcomers as $p)
                <li><a href="{{ $p->url() }}" class="group block text-center" title="{{ $p->user->name }}">@include('site.community._avatar', ['p' => $p, 'size' => 'size-11 mx-auto'])<span class="mt-1 block truncate text-[11px] text-slate-600 group-hover:text-nova-700">{{ strtok($p->user->name, ' ') }}</span></a></li>
            @endforeach
        </ul>
        <a href="{{ route('community.people') }}" class="mt-3 inline-block text-sm font-medium text-nova-700 hover:underline">Alle ansehen</a>
    </section>
@endsection
