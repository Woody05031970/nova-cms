@php
    $tabs = [
        ['Bestellungen', route('admin.shop.index'), 'admin.shop.index', 'admin.shop.orders.*'],
        ['Produkte', route('admin.shop.products.index'), 'admin.shop.products.*'],
        ['Einstellungen', route('admin.shop.settings'), 'admin.shop.settings*'],
    ];
@endphp
<nav class="mt-5 flex gap-1 border-b border-slate-200" aria-label="Shop">
    @foreach ($tabs as $t)
        @php $on = request()->routeIs(...array_slice($t, 2)); @endphp
        <a href="{{ $t[1] }}" @class(['-mb-px border-b-2 px-4 py-2 text-sm font-medium', 'border-nova-600 text-nova-700' => $on, 'border-transparent text-slate-600 hover:text-slate-900' => ! $on])>{{ $t[0] }}</a>
    @endforeach
</nav>
