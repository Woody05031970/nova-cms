<div class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-sm font-semibold text-nova-700">Kundenkonto</p>
        <h1 class="mt-1 text-3xl font-semibold tracking-tight">{{ $heading }}</h1>
    </div>
    <nav class="flex flex-wrap items-center gap-2 text-sm" aria-label="Kundenkonto">
        <a href="{{ route('account.home') }}" @class(['rounded-lg px-3 py-2 font-medium', 'bg-nova-50 text-nova-700' => request()->routeIs('account.home'), 'hover:bg-slate-50' => ! request()->routeIs('account.home')]) @if(request()->routeIs('account.home')) aria-current="page" @endif>Bestellungen</a>
        <a href="{{ route('account.edit') }}" @class(['rounded-lg px-3 py-2 font-medium', 'bg-nova-50 text-nova-700' => request()->routeIs('account.edit'), 'hover:bg-slate-50' => ! request()->routeIs('account.edit')]) @if(request()->routeIs('account.edit')) aria-current="page" @endif>Meine Daten</a>
        <form method="POST" action="{{ route('account.logout') }}">@csrf<button class="rounded-lg px-3 py-2 font-medium text-slate-600 hover:bg-slate-50" data-account-logout>Abmelden</button></form>
    </nav>
</div>
@if (session('status'))<p class="mt-6 rounded-lg bg-emerald-50 px-4 py-3 text-emerald-900" role="status">{{ session('status') }}</p>@endif
