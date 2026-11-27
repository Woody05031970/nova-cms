{{-- Adressfelder: $p = Feldpräfix (billing/shipping), $a = Werte --}}
<div class="grid gap-3 sm:grid-cols-2">
    <div><label class="nova-label" for="{{ $p }}-name">Name</label><input class="nova-input" id="{{ $p }}-name" name="{{ $p }}[name]" maxlength="160" autocomplete="{{ $ac }} name" value="{{ old($p.'.name', $a['name'] ?? '') }}"></div>
    <div><label class="nova-label" for="{{ $p }}-company">Firma <span class="font-normal text-slate-500">(optional)</span></label><input class="nova-input" id="{{ $p }}-company" name="{{ $p }}[company]" maxlength="160" autocomplete="{{ $ac }} organization" value="{{ old($p.'.company', $a['company'] ?? '') }}"></div>
    <div class="sm:col-span-2"><label class="nova-label" for="{{ $p }}-street">Straße und Hausnummer</label><input class="nova-input" id="{{ $p }}-street" name="{{ $p }}[street]" maxlength="160" autocomplete="{{ $ac }} street-address" value="{{ old($p.'.street', $a['street'] ?? '') }}"></div>
    <div><label class="nova-label" for="{{ $p }}-zip">PLZ</label><input class="nova-input" id="{{ $p }}-zip" name="{{ $p }}[zip]" maxlength="12" autocomplete="{{ $ac }} postal-code" value="{{ old($p.'.zip', $a['zip'] ?? '') }}"></div>
    <div><label class="nova-label" for="{{ $p }}-city">Ort</label><input class="nova-input" id="{{ $p }}-city" name="{{ $p }}[city]" maxlength="120" autocomplete="{{ $ac }} address-level2" value="{{ old($p.'.city', $a['city'] ?? '') }}"></div>
    <div class="sm:col-span-2"><label class="nova-label" for="{{ $p }}-country">Land</label>
        <select class="nova-input" id="{{ $p }}-country" name="{{ $p }}[country]" autocomplete="{{ $ac }} country">@foreach ($countries as $code => $label)<option value="{{ $code }}" @selected(old($p.'.country', $a['country'] ?? 'DE') === $code)>{{ $label }}</option>@endforeach</select></div>
</div>
