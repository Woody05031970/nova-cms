@extends('layouts.admin')
@section('title', $info[0])
@section('width', 'max-w-3xl')

@section('content')
    <div class="nova-card overflow-hidden p-0 text-center">
        <div class="nova-tech-bg px-6 py-12 text-white">
            <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-white/10 ring-1 ring-white/20"><x-icon name="lock" class="size-7" /></span>
            <h1 class="mt-5 text-2xl font-semibold">{{ $info[0] }} ist in Ihrer Lizenz nicht enthalten</h1>
            <p class="mx-auto mt-2 max-w-md text-white/75">{{ $info[1] }}</p>
        </div>
        <div class="space-y-4 p-6">
            <p class="text-slate-600">Sie können das Modul einzeln dazukaufen oder auf ein größeres Paket wechseln. Nach dem Kauf tragen Sie den neuen Schlüssel unter „Lizenz“ ein, und die Funktion steht sofort bereit.</p>
            <div class="flex flex-wrap justify-center gap-2">
                <a href="{{ route('admin.license') }}" class="nova-btn">Zur Lizenz</a>
                <a href="{{ route('admin.dashboard') }}" class="nova-btn-secondary">Zurück zur Übersicht</a>
            </div>
        </div>
    </div>
@endsection
