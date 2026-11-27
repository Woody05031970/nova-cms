@php $site = \App\Models\Setting::get('site_name', config('app.name')); @endphp
<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"></head>
<body style="margin:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a">
<div style="max-width:640px;margin:0 auto;padding:24px">
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px">
        @if ($forCustomer)
            <h1 style="font-size:20px;margin:0 0 8px">Vielen Dank für Ihre Bestellung!</h1>
            <p>Hallo {{ $order->name }},<br>wir haben Ihre Bestellung <strong>{{ $order->number }}</strong> vom {{ $order->created_at->format('d.m.Y') }} erhalten. Hier die Übersicht:</p>
        @else
            <h1 style="font-size:20px;margin:0 0 8px">Neue Bestellung {{ $order->number }}</h1>
            <p><a href="{{ route('admin.shop.orders.show', $order) }}">In der Verwaltung öffnen</a></p>
        @endif

        @include('mail.partials.items')

        <p style="margin-top:20px"><strong>Zahlungsart:</strong> {{ $payment?->label() ?? $order->payment_method }}</p>
        @if ($instructions)<div style="background:#f1f5f9;border-radius:8px;padding:12px 16px">{!! $instructions !!}</div>@endif

        <p><strong>Lieferadresse:</strong><br>
            {{ $order->name }}<br>@if ($order->company){{ $order->company }}<br>@endif
            {{ $order->street }}<br>{{ $order->zip }} {{ $order->city }}<br>{{ \App\Shop\ShopSettings::COUNTRIES[$order->country]['label'] ?? $order->country }}</p>
        @unless ($forCustomer)
            <p><strong>Kontakt:</strong> {{ $order->email }}@if ($order->phone), {{ $order->phone }}@endif</p>
        @endunless
        @if ($order->notes)<p><strong>Anmerkung:</strong> {{ $order->notes }}</p>@endif

        @if ($forCustomer)
            <p>Bei Fragen antworten Sie einfach auf diese E-Mail.<br>Ihr Team von {{ $site }}</p>
        @endif
    </div>

    @foreach ([$withdrawal, $terms] as $legal)
        @if ($legal)
            <div style="margin-top:16px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;font-size:13px;color:#334155">
                <h2 style="font-size:16px;margin-top:0">{{ $legal->title }}</h2>
                @foreach ($legal->blocks ?? [] as $b)
                    @if (($b['type'] ?? '') === 'text'){!! $b['data']['html'] ?? '' !!}@endif
                @endforeach
            </div>
        @endif
    @endforeach
    <p style="font-size:12px;color:#64748b;text-align:center">{{ \App\Models\Setting::get('company_name', $site) }} · {{ \App\Models\Setting::get('street') }} · {{ \App\Models\Setting::get('zip_city') }}</p>
</div>
</body></html>
