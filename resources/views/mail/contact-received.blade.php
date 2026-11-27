<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"></head>
<body style="margin:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a">
<div style="max-width:640px;margin:0 auto;padding:24px">
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px">
        <h1 style="font-size:20px;margin:0 0 12px">Neue Anfrage über Ihre Website</h1>
        <p style="margin:0 0 4px"><strong>{{ $contact->name }}</strong> &lt;{{ $contact->email }}&gt;</p>
        @if ($contact->subject)<p style="margin:0 0 12px;color:#475569">Thema: {{ $contact->subject }}</p>@endif
        <div style="white-space:pre-line;background:#f1f5f9;border-radius:8px;padding:12px 16px">{{ $contact->message }}</div>
        <p style="font-size:13px;color:#475569">Antworten Sie einfach auf diese E-Mail. Alle Anfragen finden Sie auch in der Verwaltung unter „Anfragen“.</p>
    </div>
</div>
</body></html>
