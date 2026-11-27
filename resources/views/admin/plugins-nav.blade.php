<nav class="mt-5 flex gap-1 border-b border-slate-200" aria-label="Plugins">
    @foreach ([['Installiert', route('admin.plugins'), 'admin.plugins'], ['Marktplatz', route('admin.plugins.market'), 'admin.plugins.market*']] as [$l, $u, $r])
        @php $on = request()->routeIs($r); @endphp
        <a href="{{ $u }}" @class(['-mb-px border-b-2 px-4 py-2 text-sm font-medium', 'border-nova-600 text-nova-700' => $on, 'border-transparent text-slate-600 hover:text-slate-900' => ! $on])>{{ $l }}</a>
    @endforeach
</nav>
