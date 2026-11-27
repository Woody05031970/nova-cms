@php $embed = ! empty($d['provider']) && ! empty($d['video_id']) ? \App\Support\Blocks\VideoUrl::embed($d['provider'], $d['video_id']) : null; @endphp
@if ($embed || ! empty($d['file']))
    <figure class="mx-auto mt-8 max-w-4xl px-4 sm:px-6">
        @if ($embed)
            <div class="relative aspect-video overflow-hidden rounded-xl bg-slate-900" data-video-consent data-src="{{ $embed }}" data-title="{{ $d['caption'] ?? 'Video' }}">
                <div class="absolute inset-0 flex flex-col items-center justify-center gap-4 p-6 text-center text-white">
                    <button type="button" class="grid size-16 place-items-center rounded-full bg-white/95 text-slate-900 shadow-lg hover:scale-105" aria-label="Video abspielen">
                        <svg viewBox="0 0 24 24" class="ml-1 size-7" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                    </button>
                    <p class="max-w-md text-xs text-white/80">Beim Abspielen werden Daten an {{ $d['provider'] === 'youtube' ? 'YouTube (Google)' : 'Vimeo' }} übertragen. Mehr in der Datenschutzerklärung.</p>
                </div>
            </div>
        @else
            <video src="{{ $d['file'] }}" controls preload="metadata" class="w-full rounded-xl"></video>
        @endif
        @if (! empty($d['caption']))<figcaption class="mt-2 text-center text-sm text-slate-500">{{ $d['caption'] }}</figcaption>@endif
    </figure>
@endif
