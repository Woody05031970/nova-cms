<form method="GET" action="{{ route('search') }}" role="search" class="mt-6 flex gap-2" aria-label="Website durchsuchen">
    <label for="site-search-{{ $id ?? 'main' }}" class="sr-only">Suchbegriff</label>
    <input id="site-search-{{ $id ?? 'main' }}" type="search" name="q" value="{{ $value ?? '' }}" maxlength="100" @if($autofocus ?? false) autofocus @endif
           placeholder="Wonach suchen Sie?" class="nova-input min-h-12 flex-1 text-base" autocomplete="off">
    <button class="inline-flex min-h-12 items-center gap-2 rounded-xl bg-nova-600 px-5 font-semibold text-white hover:bg-nova-700"><x-icon name="search" class="size-5" /> Suchen</button>
</form>
