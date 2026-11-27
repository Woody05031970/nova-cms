@extends('layouts.admin')
@section('title', 'KI-Assistent')
@push('head') @vite('resources/js/app.js') @endpush

@php
    $cfg = [
        'providers' => collect($providers)->map(fn ($p) => collect($p)->except('driver'))->all(),
        'provider' => old('provider', $current['provider']),
        'model' => old('model', $current['model']),
        'baseUrl' => old('base_url', $current['base_url']),
        'testUrl' => route('admin.ai.test'),
    ];
@endphp

@section('content')
    <h1 class="text-2xl font-semibold tracking-tight">KI-Assistent</h1>
    <p class="mt-1 text-slate-600">Die KI schreibt und verbessert Texte für Sie. Sie entscheiden, welcher Anbieter das übernimmt.</p>

    @if ($configured)
        <section class="nova-card mt-8 flex flex-col gap-4 border-nova-200 bg-gradient-to-br from-nova-50 to-white sm:flex-row sm:items-center">
            <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-nova-600 text-white"><x-icon name="sparkles" class="size-6" /></span>
            <div class="flex-1">
                <h2 class="font-semibold">Website-Assistent</h2>
                <p class="text-sm text-slate-600">Beantworten Sie ein paar Fragen zu Ihrem Angebot. Die KI schreibt daraus Startseite, Leistungen, Über uns und mehr als Entwurf.</p>
            </div>
            <a href="{{ route('admin.ai.wizard') }}" class="nova-btn shrink-0">Erste Seiten schreiben lassen</a>
        </section>
    @endif

    <form method="POST" action="{{ route('admin.ai.update') }}" class="mt-8 space-y-6" x-data="aiSettings(@js($cfg))" x-cloak>
        @csrf @method('PUT')
        <input type="hidden" name="provider" :value="provider">

        <section class="nova-card">
            <div class="flex items-center gap-3">
                <h2 class="font-semibold">1. Anbieter wählen</h2>
                @if ($configured)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700"><span class="size-1.5 rounded-full bg-emerald-500"></span>Eingerichtet</span>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700"><span class="size-1.5 rounded-full bg-amber-500"></span>Noch nicht eingerichtet</span>
                @endif
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3" role="radiogroup" aria-label="KI-Anbieter">
                <template x-for="(item, key) in providers" :key="key">
                    <button type="button" role="radio" :aria-checked="provider === key" @click="choose(key)"
                            class="rounded-xl border p-4 text-left transition hover:border-nova-500"
                            :class="provider === key ? 'border-nova-500 bg-nova-50 ring-2 ring-nova-200' : 'border-slate-200 bg-white'">
                        <span class="flex items-center justify-between gap-2">
                            <span class="font-medium" x-text="item.label"></span>
                            <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-500" x-text="item.region"></span>
                        </span>
                        <span class="mt-1 block text-sm text-slate-600" x-text="item.description"></span>
                    </button>
                </template>
            </div>
        </section>

        <section class="nova-card space-y-5">
            <h2 class="font-semibold">2. Zugang eintragen</h2>

            <div x-show="p.needs_key">
                <label class="nova-label" for="api_key">API-Schlüssel</label>
                <input class="nova-input font-mono" id="api_key" name="api_key" type="password" autocomplete="off" x-model="apiKey"
                       placeholder="{{ $current['key_hint'] ? 'Gespeichert: '.$current['key_hint'].' (leer lassen, um ihn zu behalten)' : 'Schlüssel hier einfügen' }}">
                <p class="nova-help">
                    Den Schlüssel bekommen Sie im Kundenkonto des Anbieters<template x-if="p.key_url"><span>: <a :href="p.key_url" target="_blank" rel="noopener" class="font-medium text-nova-700 hover:underline">Schlüssel erstellen ↗</a></span></template>.
                    Er wird verschlüsselt gespeichert. Die Kosten rechnet der Anbieter direkt mit Ihnen ab, meist nur wenige Cent pro Text.
                </p>
                @if ($current['key_hint'])
                    <label class="mt-2 flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="forget_key" value="1" class="accent-nova-600"> Gespeicherten Schlüssel löschen</label>
                @endif
            </div>

            <div x-show="p.editable_url">
                <label class="nova-label" for="base_url">Adresse der Schnittstelle</label>
                <input class="nova-input font-mono" id="base_url" name="base_url" x-model="baseUrl" :disabled="!p.editable_url" placeholder="https://…/v1">
                <p class="nova-help">Die Adresse endet meist auf /v1. Sie finden sie in der Anleitung Ihres Anbieters.</p>
            </div>

            <div>
                <label class="nova-label" for="model">Modell</label>
                <template x-if="Object.keys(p.models || {}).length">
                    <select class="nova-input" x-model="model" @change="result = null" aria-label="Modell auswählen">
                        <template x-for="(label, id) in p.models" :key="id"><option :value="id" x-text="label" :selected="id === model"></option></template>
                        <option value="" x-show="false"></option>
                    </select>
                </template>
                <input class="nova-input mt-2 font-mono text-sm" id="model" name="model" x-model="model" maxlength="120" placeholder="Name des Modells, z. B. llama3.1">
                <p class="nova-help" x-show="Object.keys(p.models || {}).length">Sie können oben eines wählen oder den genauen Namen eines anderen Modells eintragen.</p>
            </div>

            <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-5">
                <button type="button" class="nova-btn-secondary" @click="test()" :disabled="testing">
                    <span x-show="!testing">Verbindung testen</span>
                    <span x-show="testing">Teste …</span>
                </button>
                <p x-show="result" x-text="result?.message" class="text-sm" :class="result?.ok ? 'text-emerald-700' : 'text-red-700'" role="status"></p>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-600">
            <h2 class="font-semibold text-slate-900">Datenschutz</h2>
            <p class="mt-1">Wenn Sie eine KI-Funktion nutzen, schickt Nova CMS den betreffenden Text und Ihre Angaben aus dem Firmenprofil an den gewählten Anbieter. Daten Ihrer Besucher werden nie übertragen. Schließen Sie mit dem Anbieter einen Vertrag zur Auftragsverarbeitung (AVV) ab; die großen Anbieter bieten ihn im Kundenkonto an. Wer keine Daten aus der EU geben möchte, wählt Mistral oder einen eigenen KI-Server.</p>
        </section>

        <div class="flex justify-end"><button class="nova-btn">Speichern</button></div>
    </form>
@endsection
