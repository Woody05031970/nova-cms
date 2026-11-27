@extends('site.layout')
@section('title', $title.' – '.$siteName)
@section('meta')<meta name="robots" content="noindex">@endsection

@section('content')
    <section class="mx-auto max-w-xl px-4 py-24 text-center sm:px-6">
        <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-gradient-to-br from-nova-600 to-fuchsia-500 text-white shadow-lg shadow-nova-600/30"><x-icon name="mail" class="size-7" /></span>
        <h1 class="mt-6 text-3xl font-semibold tracking-tight">{{ $title }}</h1>
        <p class="mt-3 text-lg text-slate-600">{{ $text }}</p>
        @if ($unsubscribeToken)
            <form method="POST" action="{{ route('newsletter.unsubscribe.do', $unsubscribeToken) }}" class="mt-8">
                @csrf
                <button class="rounded-xl bg-slate-900 px-5 py-3 font-semibold text-white hover:bg-slate-800">Ja, abbestellen</button>
            </form>
        @else
            <a href="{{ route('home') }}" class="mt-8 inline-flex rounded-xl bg-nova-600 px-5 py-3 font-semibold text-white hover:bg-nova-700">Zur Startseite</a>
        @endif
    </section>
@endsection
