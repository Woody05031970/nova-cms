@extends('layouts.admin')
@section('title', 'Module und Preise')
@section('width', 'max-w-5xl')

@section('content')
    <a href="{{ route('admin.license-server') }}" class="text-sm text-nova-700 hover:underline">← Lizenzserver</a>
    <h1 class="mt-2 text-2xl font-semibold tracking-tight">Module und Preise</h1>
    <p class="mt-1 max-w-3xl text-slate-600">Legen Sie fest, welche Module kostenlos sind und welche Kunden dazukaufen. Kostenlose Module sind in jeder Lizenz automatisch freigeschaltet, beim nächsten Lizenzabgleich der Kunden-Website. Kostenpflichtige Module erscheinen mit Ihrem Preis im Shop und in der Modulübersicht der Verkaufswebsite.</p>

    @if ($errors->any())<div class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">@foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>@endif

    <form method="POST" action="{{ route('admin.license-server.pricing.update') }}" class="nova-card mt-8 p-0" data-pricing>
        @csrf @method('PUT')
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="px-6 py-2.5 font-medium">Modul</th><th class="px-3 py-2.5 font-medium">Kostenlos</th><th class="px-3 py-2.5 font-medium">Kostenpflichtig</th><th class="px-6 py-2.5 font-medium">Preis (€, brutto)</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($modules as $key => $m)
                        @php $kind = old('modules.'.$key.'.kind', $m['free'] ? 'free' : 'paid'); @endphp
                        <tr class="group" data-pricing-row="{{ $key }}">
                            <td class="px-6 py-3"><p class="font-medium">{{ $m['name'] }}</p><p class="max-w-md text-xs text-slate-500">{{ $m['description'] }}</p></td>
                            <td class="px-3 py-3"><label class="inline-flex items-center gap-2"><input type="radio" name="modules[{{ $key }}][kind]" value="free" class="accent-nova-600" @checked($kind === 'free')><span class="sr-only">{{ $m['name'] }} kostenlos</span></label></td>
                            <td class="px-3 py-3"><label class="inline-flex items-center gap-2"><input type="radio" name="modules[{{ $key }}][kind]" value="paid" class="accent-nova-600" @checked($kind === 'paid')><span class="sr-only">{{ $m['name'] }} kostenpflichtig</span></label></td>
                            <td class="px-6 py-3"><label class="sr-only" for="price-{{ $key }}">Preis für {{ $m['name'] }}</label>
                                <input class="nova-input w-28 py-1.5 group-has-[input[value=free]:checked]:opacity-40" id="price-{{ $key }}" name="modules[{{ $key }}][price]" inputmode="decimal" value="{{ old('modules.'.$key.'.price', number_format($m['price_cents'] / 100, 2, ',', '')) }}" @error('modules.'.$key.'.price') aria-invalid="true" @enderror></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-6 py-4">
            <p class="text-xs text-slate-500">Bereits verkaufte Lizenzen behalten ihre Module. Ein Modul wieder kostenpflichtig zu machen, nimmt es nur Kunden weg, die es nicht gekauft haben.</p>
            <button class="nova-btn">Speichern</button>
        </div>
    </form>
@endsection
