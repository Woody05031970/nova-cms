{{-- Bereich aus dem visuellen Editor. Gleiche Darstellung in resources/js/builder/render.js (Leinwand). --}}
@php
    $B = \App\Support\Blocks\Builder::class;
    $sec = $B::section($d);
    $layout = $d['layout'] ?? '1';
@endphp
<section @if(! empty($d['style']['anchor'])) id="{{ $d['style']['anchor'] }}" @endif class="{{ $sec['class'] }}" style="{{ $sec['style'] }}">
    @if ($sec['overlay'])<div class="nb-overlay" style="{{ $sec['overlay'] }}"></div>@endif
    <div class="nb-inner" @if($sec['inner']) style="{{ $sec['inner'] }}" @endif>
        <div class="nb-row" data-n="{{ count($d['cols'] ?? []) }}" style="--nb-cols:{{ $B::gridColumns($layout) }}">
            @foreach ($d['cols'] ?? [] as $col)
                @php $c = $B::column($col); @endphp
                <div class="{{ $c['class'] }}" @if($c['style']) style="{{ $c['style'] }}" @endif>
                    @foreach ($col['widgets'] ?? [] as $w)
                        @include('site.blocks.partials.widget', ['w' => $w])
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
</section>
