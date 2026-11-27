@php
    $tabs = [['admin.seo', 'Übersicht', null], ['admin.seo.competitors', 'Mitbewerber und Maßnahmen', 'seo-pro'], ['admin.geo', 'KI-Sichtbarkeit', 'geo'], ['admin.seo.redirects', 'Weiterleitungen', null]];
@endphp
<nav class="mt-5 -mb-px flex gap-1 overflow-x-auto border-b border-slate-200" aria-label="SEO-Bereiche">
    @foreach ($tabs as [$route, $label, $feature])
        @php
            $locked = $feature && ! \App\Licensing\Features::on($feature);
            $on = request()->routeIs($route === 'admin.seo' ? $route : $route.'*');
        @endphp
        <a href="{{ route($route) }}" @class(['inline-flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-2.5 text-sm font-medium whitespace-nowrap', 'border-nova-600 text-nova-700' => $on, 'border-transparent text-slate-600 hover:text-slate-900' => ! $on]) @if($on) aria-current="page" @endif>
            {{ $label }}@if ($locked)<x-icon name="lock" class="size-3.5 text-slate-400" /><span class="sr-only">(nicht in Ihrer Lizenz)</span>@endif
        </a>
    @endforeach
</nav>
