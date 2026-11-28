@php
    $B = \App\Support\Blocks\Builder::class;
    $wd = $w['data'] ?? [];
    $ws = $w['style'] ?? [];
    $box = $B::widget($w);
    $col = fn ($k = 'color') => $B::color($ws[$k] ?? null);
    $ext = fn ($u) => is_string($u) && str_starts_with($u, 'http');
    $listIcons = ['check' => 'M5 12l5 5L20 7', 'arrow' => 'M5 12h14M13 6l6 6-6 6', 'dot' => 'M12 12h.01', 'star' => \App\Support\Icons::PATHS['star']];
    $show = match ($w['type']) {
        'image' => ! empty($wd['url']),
        'button' => (! empty($wd['label']) && ! empty($wd['url'])) || (! empty($wd['label2']) && ! empty($wd['url2'])),
        'heading' => ($wd['text'] ?? '') !== '',
        'video' => ! empty($wd['video_id']) || ! empty($wd['file']),
        'gallery' => ! empty($wd['items']),
        'list' => ! empty($wd['items']),
        default => true,
    };
@endphp
@if ($show)
<div class="{{ $box['class'] }}" style="{{ $box['style'] }}">
@switch($w['type'])
    @case('heading')
        @php $tag = $ws['tag'] ?? 'h2'; @endphp
        <{{ $tag }} class="nb-heading nb-hs-{{ $ws['size'] ?? 'l' }} nb-fw-{{ $ws['weight'] ?? 'semibold' }}{{ ! empty($ws['gradient']) ? ' nb-grad' : '' }}" @if($col()) style="color:{{ $col() }}" @endif>@if(! empty($wd['link']))<a href="{{ $wd['link'] }}" @if($ext($wd['link'])) target="_blank" rel="noopener" @endif>{{ $wd['text'] }}</a>@else{{ $wd['text'] }}@endif</{{ $tag }}>
        @break
    @case('text')
        <div class="nova-prose nb-text nb-ts-{{ $ws['size'] ?? 'm' }}" @if($col()) style="color:{{ $col() }}" @endif>{!! $wd['html'] ?? '' !!}</div>
        @break
    @case('image')
        <figure class="nb-figure" style="width:{{ (int) ($ws['width'] ?? 100) }}%">
            @if (! empty($wd['link']))<a href="{{ $wd['link'] }}" @if($ext($wd['link'])) target="_blank" rel="noopener" @endif>@endif
            <img src="{{ $wd['url'] }}" alt="{{ $wd['alt'] ?? '' }}" loading="lazy" class="nb-img nb-r-{{ $ws['radius'] ?? 'm' }}{{ ! empty($ws['shadow']) ? ' nb-shadow' : '' }}" @if(($ws['ratio'] ?? 'auto') !== 'auto') style="aspect-ratio:{{ $B::ratio($ws['ratio']) }}" @endif>
            @if (! empty($wd['link']))</a>@endif
            @if (! empty($wd['caption']))<figcaption class="nb-caption">{{ $wd['caption'] }}</figcaption>@endif
        </figure>
        @break
    @case('button')
        <div class="nb-btns{{ ! empty($ws['full']) ? ' nb-btns-full' : '' }}">
            @foreach ([['label', 'url', 'variant'], ['label2', 'url2', 'variant2']] as [$l, $u, $v])
                @if (! empty($wd[$l]) && ! empty($wd[$u]))
                    <a href="{{ $wd[$u] }}" class="nb-btn nb-btn-{{ $ws[$v] ?? ($v === 'variant' ? 'primary' : 'outline') }} nb-bs-{{ $ws['size'] ?? 'm' }}" @if($ext($wd[$u])) target="_blank" rel="noopener" @endif>{{ $wd[$l] }}</a>
                @endif
            @endforeach
        </div>
        @break
    @case('icon')
        <div class="nb-ib nb-ib-{{ $ws['layout'] ?? 'top' }}">
            <span class="nb-ib-icon nb-ib-{{ $ws['shape'] ?? 'circle' }} nb-is-{{ $ws['size'] ?? 'm' }}" @if($col()) style="color:{{ $col() }}" @endif>{!! \App\Support\Icons::svg($wd['icon'] ?? 'sparkles', 'nb-svg') !!}</span>
            <div class="nb-ib-body">
                @if (($wd['title'] ?? '') !== '')<h3 class="nb-ib-title">@if(! empty($wd['link']))<a href="{{ $wd['link'] }}">{{ $wd['title'] }}</a>@else{{ $wd['title'] }}@endif</h3>@endif
                @if (($wd['text'] ?? '') !== '')<p class="nb-ib-text">{{ $wd['text'] }}</p>@endif
            </div>
        </div>
        @break
    @case('list')
        <ul class="nb-list nb-ls-{{ $ws['size'] ?? 'm' }}">
            @foreach ($wd['items'] as $item)
                <li><span class="nb-li-icon" @if($col()) style="color:{{ $col() }}" @endif><svg class="nb-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ ($ws['icon'] ?? 'check') === 'dot' ? 6 : 2.2 }}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $listIcons[$ws['icon'] ?? 'check'] }}"/></svg></span><span>{{ $item }}</span></li>
            @endforeach
        </ul>
        @break
    @case('counter')
        <div class="nb-counter nb-cs-{{ $ws['size'] ?? 'l' }}">
            <div class="nb-counter-num" @if($col()) style="color:{{ $col() }}" @endif>{{ ($wd['prefix'] ?? '').($wd['number'] ?? '').($wd['suffix'] ?? '') }}</div>
            @if (($wd['label'] ?? '') !== '')<div class="nb-counter-label">{{ $wd['label'] }}</div>@endif
        </div>
        @break
    @case('quote')
        <figure class="nb-quote nb-quote-{{ $ws['variant'] ?? 'card' }}">
            @if (! empty($ws['stars']))<div class="nb-stars" aria-label="5 von 5 Sternen">★★★★★</div>@endif
            <blockquote>{{ $wd['text'] ?? '' }}</blockquote>
            @if (($wd['name'] ?? '') !== '' || ! empty($wd['image']))
                <figcaption>@if (! empty($wd['image']))<img src="{{ $wd['image'] }}" alt="" loading="lazy">@endif<span><strong>{{ $wd['name'] ?? '' }}</strong>@if(($wd['role'] ?? '') !== '')<span>{{ $wd['role'] }}</span>@endif</span></figcaption>
            @endif
        </figure>
        @break
    @case('video')
        @php $embed = ! empty($wd['provider']) && ! empty($wd['video_id']) ? \App\Support\Blocks\VideoUrl::embed($wd['provider'], $wd['video_id']) : null; @endphp
        <figure class="nb-video">
            @if ($embed)
                <div class="relative aspect-video overflow-hidden rounded-xl bg-slate-900 bg-cover bg-center" @if(! empty($wd['poster'])) style="background-image: url('{{ $wd['poster'] }}')" @endif data-video-consent data-src="{{ $embed }}" data-title="{{ $wd['caption'] ?? 'Video' }}">
                    <div class="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-gradient-to-t from-black/70 via-black/20 to-black/10 p-6 text-center text-white">
                        <button type="button" class="grid size-16 place-items-center rounded-full bg-white/95 text-slate-900 shadow-lg hover:scale-105" aria-label="Video abspielen"><svg viewBox="0 0 24 24" class="ml-1 size-7" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></button>
                        <p class="max-w-md text-xs text-white/80">Beim Abspielen werden Daten an {{ $wd['provider'] === 'youtube' ? 'YouTube (Google)' : 'Vimeo' }} übertragen. Mehr in der Datenschutzerklärung.</p>
                    </div>
                </div>
            @else
                <video src="{{ $wd['file'] }}" controls preload="metadata" @if(! empty($wd['poster'])) poster="{{ $wd['poster'] }}" @endif class="w-full rounded-xl"></video>
            @endif
            @if (! empty($wd['caption']))<figcaption class="nb-caption">{{ $wd['caption'] }}</figcaption>@endif
        </figure>
        @break
    @case('gallery')
        <div class="nb-gallery nb-gg-{{ $ws['gap'] ?? 's' }}" style="--nb-gcols:{{ (int) ($ws['cols'] ?? 3) }}">
            @foreach ($wd['items'] as $img)
                <img src="{{ $img['url'] }}" alt="{{ $img['alt'] ?? '' }}" loading="lazy" class="nb-r-{{ $ws['radius'] ?? 's' }}" style="aspect-ratio:{{ $B::ratio($ws['ratio'] ?? '1:1') }}">
            @endforeach
        </div>
        @break
    @case('spacer')
        <div class="nb-spacer" style="height:{{ (int) ($ws['height'] ?? 48) }}px"></div>
        @break
    @case('divider')
        <hr class="nb-divider" style="border-top:{{ (int) ($ws['weight'] ?? 1) }}px {{ $ws['line'] ?? 'solid' }} {{ $col() ?? '#cbd5e1' }};width:{{ (int) ($ws['width'] ?? 100) }}%">
        @break
    @case('html')
        {!! $wd['code'] ?? '' !!}
        @break
@endswitch
</div>
@endif
