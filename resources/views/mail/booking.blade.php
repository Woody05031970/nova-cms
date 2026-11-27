@php $b = $booking; @endphp
<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"></head>
<body style="margin:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a">
<div style="max-width:640px;margin:0 auto;padding:24px">
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px">
        @if ($kind === 'owner')
            <h1 style="font-size:20px;margin:0 0 12px">Neue Terminbuchung</h1>
            <p style="margin:0 0 12px">{{ $b->name }} hat einen Termin {{ $b->status === 'pending' ? 'angefragt. Bitte bestätigen oder absagen Sie ihn in der Verwaltung unter „Termine“.' : 'gebucht.' }}</p>
        @else
            <h1 style="font-size:20px;margin:0 0 12px">{{ \App\Mail\BookingMail::SUBJECTS[$kind] }}</h1>
            <p style="margin:0 0 12px">Hallo {{ $b->name }},</p>
            <p style="margin:0 0 12px">{{ match ($kind) {
                'requested' => 'vielen Dank für Ihre Anfrage. Wir prüfen den Termin und melden uns mit einer Bestätigung.',
                'confirmed' => 'wir freuen uns auf Sie. Hier sind die Angaben zu Ihrem Termin. Den Termin finden Sie auch als Kalendereintrag im Anhang.',
                'cancelled' => 'leider findet der folgende Termin nicht statt. Gern können Sie auf unserer Website einen neuen Termin buchen.',
                'reminder' => 'wir möchten Sie an Ihren Termin morgen erinnern.',
            } }}</p>
        @endif
        <table style="width:100%;border-collapse:collapse;background:#f1f5f9;border-radius:8px">
            <tr><td style="padding:8px 12px;color:#475569">Leistung</td><td style="padding:8px 12px"><strong>{{ $b->service_name }}</strong></td></tr>
            <tr><td style="padding:8px 12px;color:#475569">Termin</td><td style="padding:8px 12px">{{ $b->when() }}</td></tr>
            @if ($kind === 'owner')
                <tr><td style="padding:8px 12px;color:#475569">Kunde</td><td style="padding:8px 12px">{{ $b->name }}, {{ $b->email }}@if($b->phone), {{ $b->phone }}@endif</td></tr>
                @if ($b->note)<tr><td style="padding:8px 12px;color:#475569">Nachricht</td><td style="padding:8px 12px;white-space:pre-line">{{ $b->note }}</td></tr>@endif
            @elseif (trim(\App\Models\Setting::get('street').' '.\App\Models\Setting::get('zip_city')))
                <tr><td style="padding:8px 12px;color:#475569">Ort</td><td style="padding:8px 12px">{{ trim(\App\Models\Setting::get('street').', '.\App\Models\Setting::get('zip_city'), ', ') }}</td></tr>
            @endif
        </table>
        @if ($kind !== 'owner' && $kind !== 'cancelled')
            <p style="margin:16px 0 0;font-size:14px">Sie können nicht? <a href="{{ route('booking.cancel', $b->token) }}" style="color:#4f46e5">Termin absagen</a></p>
        @endif
        <p style="margin:16px 0 0;font-size:13px;color:#475569">{{ $site }}</p>
    </div>
</div>
</body></html>
