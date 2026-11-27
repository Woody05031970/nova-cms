@extends('site.layout')
@section('title', ($page->meta_title ?: $page->title).($page->is_home && ! $page->meta_title ? '' : ' – '.$siteName))
@section('description', $page->meta_description ?: \Illuminate\Support\Str::limit($page->plainText(), 155))
@section('meta')
    @php
        $seo = app(\App\Support\Seo\SiteSeo::class);
        $shareImage = $seo->shareImage($page);
        $desc = $page->meta_description ?: \Illuminate\Support\Str::limit($page->plainText(), 155);
    @endphp
    @if ($preview || ! \App\Support\Seo\SiteSeo::indexingAllowed())<meta name="robots" content="noindex">@endif
    @unless ($preview)<link rel="canonical" href="{{ $page->url() }}">@endunless
    <meta property="og:title" content="{{ $page->meta_title ?: $page->title }}">
    <meta property="og:description" content="{{ $desc }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $page->url() }}">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:locale" content="de_DE">
    @if ($shareImage)<meta property="og:image" content="{{ $shareImage }}"><meta name="twitter:card" content="summary_large_image">@endif
    @if ($page->is_home && ! $preview && ($ld = $seo->businessJsonLd()))<script type="application/ld+json">{!! $ld !!}</script>@endif
@endsection
@section('banner')
    @if ($preview && empty($live))
        <div class="bg-amber-100 px-4 py-2 text-center text-sm text-amber-900">Vorschau: {{ $page->isPublished() ? 'Diese Seite ist online.' : 'Diese Seite ist noch ein Entwurf und für Besucher nicht sichtbar.' }}
            <a href="{{ route('admin.pages.edit', $page) }}" class="ml-2 font-medium underline">Zurück zum Bearbeiten</a></div>
    @endif
@endsection

@section('content')
    @php
        $blocks = $page->blocks ?? [];
        $startsWithHero = ($blocks[0]['type'] ?? null) === 'hero';
    @endphp
    @unless ($startsWithHero)
        <div class="mx-auto max-w-3xl px-4 pt-12 sm:px-6"><h1 class="text-4xl font-semibold tracking-tight sm:text-5xl">{{ $page->title }}</h1></div>
    @endunless
    <div class="pb-16">
        @foreach ($blocks as $block)
            @includeIf('site.blocks.'.$block['type'], ['d' => $block['data'], 'first' => $loop->first])
        @endforeach
    </div>
@endsection
