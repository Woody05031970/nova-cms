@php $tabs = [['admin.seo', 'Übersicht'], ['admin.seo.competitors', 'Mitbewerber und Maßnahmen'], ['admin.seo.redirects', 'Weiterleitungen']]; @endphp
<nav class="mt-5 -mb-px flex gap-1 overflow-x-auto border-b border-slate-200" aria-label="SEO-Bereiche">
    @foreach ($tabs as [$route, $label])
        <a href="{{ route($route) }}" @class(['shrink-0 border-b-2 px-3 py-2.5 text-sm font-medium whitespace-nowrap', 'border-nova-600 text-nova-700' => request()->routeIs($route), 'border-transparent text-slate-600 hover:text-slate-900' => ! request()->routeIs($route)]) @if(request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
