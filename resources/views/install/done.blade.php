@extends('layouts.install')

@section('content')
    <div class="text-center">
        <div class="mx-auto grid size-14 place-items-center rounded-full bg-emerald-100 text-emerald-700"><x-icon name="check" class="size-7" /></div>
        <h1 class="mt-4 text-2xl font-semibold tracking-tight">Geschafft!</h1>
        <p class="mt-2 text-slate-600">Nova CMS ist installiert. Melden Sie sich jetzt an. Das Dashboard zeigt Ihnen Schritt für Schritt, was als Nächstes zu tun ist.</p>
        <a href="{{ route('login') }}" class="nova-btn mt-6">Zur Anmeldung <x-icon name="arrow" class="size-4" /></a>
    </div>
@endsection
