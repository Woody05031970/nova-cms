<section class="mx-auto max-w-md px-4 py-16 sm:px-6">
    @if (session('status'))<p class="mb-6 rounded-lg bg-emerald-50 px-4 py-3 text-emerald-900" role="status">{{ session('status') }}</p>@endif
    <p class="text-sm font-semibold text-nova-700">{{ $eyebrow ?? \App\Support\Members\Members::title() }}</p>
    <h1 class="mt-1 text-3xl font-semibold tracking-tight">{{ $heading }}</h1>
    @isset($lead)<p class="mt-3 text-slate-600">{{ $lead }}</p>@endisset
    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">@yield('form')</div>
</section>
