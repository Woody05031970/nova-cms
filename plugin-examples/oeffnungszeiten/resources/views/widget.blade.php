<dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-1 text-sm">
    @foreach ($tage as $t)
        <dt class="text-slate-500">{{ $t['tag'] }}</dt>
        <dd class="{{ $t['geschlossen'] ? 'text-slate-400' : 'font-medium' }}">{{ $t['geschlossen'] ? 'geschlossen' : $t['von'].' bis '.$t['bis'].' Uhr' }}</dd>
    @endforeach
</dl>
<a href="{{ url('admin/oeffnungszeiten') }}" class="mt-4 inline-block text-sm font-medium text-nova-700 hover:underline">Bearbeiten</a>
