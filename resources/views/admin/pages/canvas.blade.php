{{-- Leinwand im visuellen Editor: echte Website mit Kopf und Fuß, den Inhalt zeichnet resources/js/canvas.js --}}
@extends('site.layout')
@section('title', $page->title)
@section('meta')<meta name="robots" content="noindex">@endsection
@section('content')
    <div id="nbx-root"></div>
    <style>
        html { scroll-padding-top: 5rem; }
        body { --nbx: #2563eb; --nbx-sec: #f97316; }
        .nova-header, .nova-footer, footer { pointer-events: none; opacity: .92; }
        [data-site-header] { position: relative !important; }
        [data-nb-section], .nbx-legacy { position: relative; outline: 1px dashed transparent; outline-offset: -1px; transition: outline-color .12s; }
        [data-nb-section]:hover, .nbx-legacy:hover { outline-color: rgb(249 115 22 / .55); }
        [data-nb-section].nbx-sel, .nbx-legacy.nbx-sel { outline: 2px solid var(--nbx-sec); outline-offset: -2px; }
        [data-nb-col] { position: relative; min-height: 3rem; outline: 1px dashed transparent; outline-offset: 2px; }
        [data-nb-section]:hover [data-nb-col] { outline-color: rgb(148 163 184 / .5); }
        [data-nb-col].nbx-sel { outline: 2px solid rgb(100 116 139); }
        [data-nb-widget] { position: relative; cursor: default; outline: 1px solid transparent; outline-offset: 4px; border-radius: 2px; }
        [data-nb-widget]:hover { outline-color: rgb(37 99 235 / .5); }
        [data-nb-widget].nbx-sel { outline: 2px solid var(--nbx); }
        [data-nb-edit] { cursor: text; outline: none; }
        [data-nb-edit]:empty::before { content: attr(data-ph); opacity: .4; }
        .nova-prose[data-nb-edit]:empty::before, .nova-prose[data-nb-edit] > p:only-child:empty::before { content: attr(data-ph); opacity: .4; }
        .nbx-stools { position: absolute; top: 0; left: 50%; transform: translate(-50%, -100%); display: none; z-index: 30; gap: 1px; padding: 0 2px; border-radius: 8px 8px 0 0; background: var(--nbx-sec); color: #fff; }
        [data-nb-section]:hover > .nbx-stools, .nbx-legacy:hover > .nbx-stools, .nbx-sel > .nbx-stools { display: flex; }
        [data-nb-section]:first-of-type > .nbx-stools { transform: translate(-50%, 0); border-radius: 0 0 8px 8px; }
        .nbx-wtools { position: absolute; top: -12px; right: -6px; display: none; z-index: 31; gap: 1px; padding: 0 2px; border-radius: 6px; background: var(--nbx); color: #fff; box-shadow: 0 4px 12px rgb(0 0 0 / .2); }
        [data-nb-widget]:hover > .nbx-wtools, [data-nb-widget].nbx-sel > .nbx-wtools { display: flex; }
        .nbx-wlabel { padding: 0 6px; font: 600 11px/26px system-ui, sans-serif; letter-spacing: .02em; white-space: nowrap; }
        .nbx-b { display: inline-flex; align-items: center; gap: 4px; height: 26px; min-width: 26px; justify-content: center; padding: 0 4px; border: 0; background: none; color: inherit; cursor: pointer; font: 600 12px system-ui, sans-serif; border-radius: 4px; }
        .nbx-b:hover { background: rgb(255 255 255 / .2); }
        .nbx-grip { cursor: grab; }
        .nbx-i { width: 15px; height: 15px; }
        .nbx-empty { display: flex; width: 100%; min-height: 7rem; flex-direction: column; align-items: center; justify-content: center; gap: .4rem; border: 2px dashed rgb(148 163 184 / .7); border-radius: 10px; background: rgb(248 250 252 / .6); color: #64748b; font: 13px/1.35 system-ui, sans-serif; text-align: center; cursor: pointer; }
        .nbx-empty:hover { border-color: var(--nbx); color: var(--nbx); }
        .nbx-empty .nbx-i { width: 22px; height: 22px; }
        .nb-dark .nbx-empty { background: rgb(255 255 255 / .06); color: rgb(255 255 255 / .75); }
        .nbx-ph { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .4rem; min-height: 9rem; border-radius: 12px; background: repeating-linear-gradient(45deg, rgb(148 163 184 / .12) 0 10px, rgb(148 163 184 / .2) 10px 20px); color: #64748b; font: 13px system-ui, sans-serif; }
        .nbx-ph-icon { width: 28px; height: 28px; }
        .nbx-code { display: flex; flex-direction: column; align-items: center; gap: .3rem; padding: 1.5rem; border: 1px dashed #94a3b8; border-radius: 10px; color: #64748b; font: 13px system-ui, sans-serif; }
        .nbx-code small { font-family: ui-monospace, monospace; opacity: .7; }
        .nbx-code .nb-svg { width: 22px; height: 22px; }
        .nbx-insert { position: relative; height: 0; z-index: 25; }
        .nbx-insert button { position: absolute; left: 50%; top: 0; transform: translate(-50%, -50%) scale(.8); display: grid; place-items: center; width: 28px; height: 28px; border-radius: 99px; border: 0; background: var(--nbx-sec); color: #fff; opacity: 0; cursor: pointer; box-shadow: 0 2px 8px rgb(0 0 0 / .25); transition: .12s; }
        .nbx-insert:hover button, .nbx-insert button:focus { opacity: 1; transform: translate(-50%, -50%) scale(1); }
        .nbx-insert::before { content: ''; position: absolute; inset: -10px 0; }
        .nbx-addzone { margin: 2.5rem auto 1rem; max-width: 56rem; padding: 1.75rem 1rem; border: 2px dashed #cbd5e1; border-radius: 14px; text-align: center; color: #64748b; font: 13px/1.5 system-ui, sans-serif; background: rgb(255 255 255 / .6); }
        .nbx-addzone.is-open { border-color: var(--nbx-sec); background: #fff; }
        .nbx-addbtns { display: flex; justify-content: center; gap: .5rem; margin-bottom: .6rem; }
        .nbx-round { display: grid; place-items: center; width: 42px; height: 42px; border-radius: 99px; border: 0; background: var(--nbx-sec); color: #fff; cursor: pointer; box-shadow: 0 4px 12px rgb(249 115 22 / .35); }
        .nbx-round.nbx-alt { background: #334155; box-shadow: none; }
        .nbx-round .nbx-i { width: 20px; height: 20px; }
        .nbx-layouts p { margin-bottom: .75rem; font-weight: 600; color: #0f172a; }
        .nbx-layouts > div { display: grid; grid-template-columns: repeat(auto-fill, minmax(92px, 1fr)); gap: .5rem; margin-bottom: .75rem; }
        .nbx-layouts button[data-layout] { display: flex; gap: 3px; height: 46px; padding: 6px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; cursor: pointer; }
        .nbx-layouts button[data-layout]:hover { border-color: var(--nbx-sec); }
        .nbx-layouts button[data-layout] i { display: block; height: 100%; border-radius: 3px; background: #cbd5e1; }
        .nbx-layouts button[data-layout]:hover i { background: var(--nbx-sec); }
        .nbx-link { border: 0; background: none; color: #64748b; text-decoration: underline; cursor: pointer; }
        .nbx-marker { position: absolute; z-index: 60; display: none; height: 4px; border-radius: 4px; background: var(--nbx); box-shadow: 0 0 0 3px rgb(37 99 235 / .2); pointer-events: none; }
        .nbx-marker.is-top { background: var(--nbx-sec); box-shadow: 0 0 0 3px rgb(249 115 22 / .25); }
        .nbx-loading { padding: 3rem 1rem; text-align: center; color: #94a3b8; font: 13px system-ui, sans-serif; }
        .nbx-legacy-body { pointer-events: none; }
        .nbx-legacy-body [data-reveal] { opacity: 1 !important; transform: none !important; }
        .nbx-title { position: relative; }
        .nbx-title:hover h1 { outline: 1px solid rgb(37 99 235 / .5); outline-offset: 6px; }
        .nbx-title.nbx-sel h1 { outline: 2px solid var(--nbx); outline-offset: 6px; }
        /* Ausgeblendete Elemente bleiben in der Leinwand blass sichtbar */
        @media (max-width: 639px) { .nb-hide-phone { display: block !important; opacity: .3; } }
        @media (min-width: 640px) and (max-width: 1023px) { .nb-hide-tablet { display: block !important; opacity: .3; } }
        @media (min-width: 1024px) { .nb-hide-desktop { display: block !important; opacity: .3; } }
    </style>
    @vite('resources/js/canvas.js')
@endsection
