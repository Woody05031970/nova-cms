@extends('layouts.admin')
@section('title', 'Anfragen')
@section('width', 'max-w-5xl')

@section('content')
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Anfragen</h1>
        <p class="mt-1 text-slate-600">Nachrichten aus dem Kontaktformular Ihrer Website. Eine Kopie geht zusätzlich an Ihre Kontakt-E-Mail, sofern der E-Mail-Versand eingerichtet ist.</p>
    </div>

    @if ($messages->isEmpty())
        <div class="nova-card mt-6 text-center">
            <x-icon name="inbox" class="mx-auto size-10 text-slate-300" />
            <p class="mt-3 font-medium">Noch keine Anfragen</p>
            <p class="mt-1 text-sm text-slate-500">Fügen Sie einer Seite den Baustein „Kontaktformular“ hinzu, damit Besucher Ihnen schreiben können.</p>
        </div>
    @else
        <div class="mt-6 space-y-3">
            @foreach ($messages as $m)
                <article @class(['nova-card p-5', 'ring-2 ring-nova-200' => ! $m->read_at]) data-message>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold">{{ $m->name }} <a class="font-normal text-nova-700 hover:underline" href="mailto:{{ $m->email }}?subject={{ rawurlencode('Ihre Anfrage'.($m->subject ? ': '.$m->subject : '')) }}">{{ $m->email }}</a></p>
                            <p class="text-sm text-slate-500">{{ $m->created_at->format('d.m.Y, H:i') }} Uhr @if($m->subject) · {{ $m->subject }} @endif @if($m->page) · über „{{ $m->page->title }}“ @endif</p>
                        </div>
                        <div class="flex items-center gap-2">
                            @unless ($m->read_at)<span class="rounded-full bg-nova-50 px-2 py-0.5 text-xs font-semibold text-nova-700">Neu</span>@endunless
                            <form method="POST" action="{{ route('admin.messages.read', $m) }}">@csrf @method('PATCH')
                                <button class="nova-btn-secondary px-3 py-1.5 text-xs">{{ $m->read_at ? 'Als ungelesen markieren' : 'Als gelesen markieren' }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.messages.destroy', $m) }}" onsubmit="return confirm('Diese Anfrage endgültig löschen?')">@csrf @method('DELETE')
                                <button class="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-700" title="Löschen"><x-icon name="trash" class="size-4" /></button>
                            </form>
                        </div>
                    </div>
                    <p class="mt-3 whitespace-pre-line text-slate-700">{{ $m->message }}</p>
                </article>
            @endforeach
        </div>
        <div class="mt-6">{{ $messages->links() }}</div>
    @endif
@endsection
