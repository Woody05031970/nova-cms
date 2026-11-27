@php $slides = $d['slides'] ?? []; @endphp
@if (count($slides))
    <section class="relative mx-auto mt-8 max-w-6xl px-4 sm:px-6" data-slider data-autoplay="{{ ($d['autoplay'] ?? true) ? '1' : '0' }}" aria-roledescription="Karussell" aria-label="Bildergalerie">
        <div class="nova-slider-track flex overflow-x-auto rounded-xl">
            @foreach ($slides as $i => $slide)
                <figure class="relative w-full shrink-0" aria-roledescription="Folie" aria-label="{{ $i + 1 }} von {{ count($slides) }}">
                    <img src="{{ $slide['url'] }}" alt="{{ $slide['alt'] ?? '' }}" class="aspect-[16/8] w-full object-cover" @if($i > 0) loading="lazy" @endif>
                    @if (! empty($slide['caption']))
                        <figcaption class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-6 pt-16 text-lg font-medium text-white">{{ $slide['caption'] }}</figcaption>
                    @endif
                </figure>
            @endforeach
        </div>
        @if (count($slides) > 1)
            <button type="button" data-prev class="absolute top-1/2 left-6 grid size-10 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-slate-800 shadow hover:bg-white sm:left-9" aria-label="Vorheriges Bild">‹</button>
            <button type="button" data-next class="absolute top-1/2 right-6 grid size-10 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-slate-800 shadow hover:bg-white sm:right-9" aria-label="Nächstes Bild">›</button>
            <div class="mt-3 flex justify-center gap-2">
                @foreach ($slides as $i => $slide)
                    <button type="button" data-dot class="size-2.5 rounded-full bg-slate-300 aria-[current=true]:bg-nova-600" aria-label="Bild {{ $i + 1 }}"></button>
                @endforeach
            </div>
        @endif
    </section>
@endif
