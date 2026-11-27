<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Gleich zurück – {{ $siteName }}</title>
    @vite(['resources/css/app.css'])
    <style>{!! \App\Support\Design\SiteDesign::css() !!}</style>
</head>
<body class="nova-site grid min-h-screen place-items-center bg-slate-50 px-4 font-sans text-slate-900 antialiased">
    <main class="max-w-lg text-center" data-maintenance>
        @php $logo = \App\Support\Design\SiteDesign::values()['logo']; @endphp
        @if ($logo)<img src="{{ $logo }}" alt="{{ $siteName }}" class="mx-auto h-14 w-auto">@else<p class="text-xl font-semibold">{{ $siteName }}</p>@endif
        <h1 class="mt-8 text-3xl font-semibold tracking-tight">Wir sind gleich zurück</h1>
        <p class="mt-3 text-lg text-slate-600">{{ $message }}</p>
        @if ($mail = \App\Models\Setting::get('contact_email'))<p class="mt-6 text-sm text-slate-600">Sie erreichen uns per E-Mail: <a class="font-medium text-nova-700 underline" href="mailto:{{ $mail }}">{{ $mail }}</a></p>@endif
    </main>
</body>
</html>
