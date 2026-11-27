@php $p = $sv ? 's'.$sv->id : 'new'; @endphp
<form method="POST" action="{{ $action }}" class="grid gap-3 sm:grid-cols-6">
    @csrf @if ($method !== 'POST') @method($method) @endif
    <div class="sm:col-span-3"><label class="nova-label" for="{{ $p }}-name">Name</label><input class="nova-input" id="{{ $p }}-name" name="name" required maxlength="120" value="{{ $sv?->name }}" placeholder="z. B. Erstberatung"></div>
    <div><label class="nova-label" for="{{ $p }}-dur">Dauer (Min.)</label><input class="nova-input" type="number" id="{{ $p }}-dur" name="duration" min="5" max="600" required value="{{ $sv?->duration ?? 30 }}"></div>
    <div><label class="nova-label" for="{{ $p }}-buf">Pause danach</label><input class="nova-input" type="number" id="{{ $p }}-buf" name="buffer" min="0" max="240" value="{{ $sv?->buffer ?? 0 }}"></div>
    <div><label class="nova-label" for="{{ $p }}-price">Preis (€)</label><input class="nova-input" id="{{ $p }}-price" name="price" inputmode="decimal" value="{{ $sv && $sv->price_cents !== null ? number_format($sv->price_cents / 100, 2, ',', '') : '' }}" placeholder="optional"></div>
    <div class="sm:col-span-6"><label class="nova-label" for="{{ $p }}-desc">Beschreibung</label><input class="nova-input" id="{{ $p }}-desc" name="description" maxlength="1000" value="{{ $sv?->description }}"></div>
    <input type="hidden" name="is_active" value="0">
    <label class="flex items-center gap-2 text-sm sm:col-span-4"><input type="checkbox" name="is_active" value="1" class="accent-nova-600" @checked($sv?->is_active ?? true)> Online buchbar</label>
    <div class="sm:col-span-2 sm:text-right"><button class="nova-btn w-full sm:w-auto">{{ $sv ? 'Speichern' : 'Leistung anlegen' }}</button></div>
</form>
