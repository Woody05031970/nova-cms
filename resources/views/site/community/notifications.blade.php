@extends('site.community._layout')
@section('ctitle', 'Benachrichtigungen')
@section('cnoindex', '1')
@section('main')
    <h1 class="text-2xl font-semibold tracking-tight">Benachrichtigungen</h1>
    @php $icons = ['like' => 'heart', 'comment' => 'chat', 'mention' => 'user', 'follow' => 'users', 'message' => 'mail', 'reply' => 'community']; @endphp
    <ul class="mt-5 divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white" data-notifications>
        @forelse ($items as $n)
            <li @class(['bg-nova-50/50' => ! $n->read_at])>
                <a href="{{ $n->url }}" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50">
                    <span class="relative">@include('site.community._avatar', ['p' => $n->actor?->communityProfile, 'size' => 'size-10'])<span class="absolute -right-1 -bottom-1 grid size-5 place-items-center rounded-full bg-white text-nova-600 shadow ring-1 ring-slate-200"><x-icon :name="$icons[$n->type] ?? 'bell'" class="size-3" /></span></span>
                    <span class="min-w-0 flex-1 text-sm"><span class="block text-slate-800">{{ $n->text }}</span><span class="text-xs text-slate-500">{{ $n->created_at->locale('de')->diffForHumans() }}</span></span>
                    @unless ($n->read_at)<span class="size-2 rounded-full bg-fuchsia-600" aria-label="neu"></span>@endunless
                </a>
            </li>
        @empty
            <li class="p-8 text-center text-sm text-slate-600">Noch keine Benachrichtigungen.</li>
        @endforelse
    </ul>
@endsection
