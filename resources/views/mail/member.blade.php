<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"></head>
<body style="margin:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a">
<div style="max-width:640px;margin:0 auto;padding:24px">
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px">
        @if ($kind === 'owner')
            <h1 style="font-size:20px;margin:0 0 12px">Neue Registrierung</h1>
            <p style="margin:0 0 12px">{{ $user->name }} ({{ $user->email }}) hat sich für den {{ \App\Support\Members\Members::title() }} registriert{{ $user->role === 'member_pending' ? ' und wartet auf Ihre Freigabe' : '' }}.</p>
            <p style="margin:20px 0"><a href="{{ $url }}" style="display:inline-block;background:#4f46e5;color:#fff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:bold">Mitglieder ansehen</a></p>
        @else
            <p style="margin:0 0 12px">Hallo {{ $user->name }},</p>
            <p style="margin:0 0 12px">{{ match ($kind) {
                'invite' => 'Sie wurden in den '.\App\Support\Members\Members::title().' von '.$site.' eingeladen. Legen Sie über den Knopf Ihr eigenes Passwort fest, danach können Sie sich jederzeit anmelden.',
                'approved' => 'Ihr Zugang zum '.\App\Support\Members\Members::title().' von '.$site.' ist jetzt freigeschaltet. Sie können sich ab sofort anmelden.',
                default => 'Sie haben ein neues Passwort angefordert. Über den Knopf legen Sie es fest. Der Link gilt 60 Minuten. Wenn Sie das nicht waren, können Sie diese E-Mail einfach ignorieren.',
            } }}</p>
            <p style="margin:20px 0"><a href="{{ $url }}" style="display:inline-block;background:#4f46e5;color:#fff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:bold">{{ match ($kind) { 'invite' => 'Passwort festlegen', 'approved' => 'Jetzt anmelden', default => 'Neues Passwort festlegen' } }}</a></p>
            <p style="margin:0;font-size:12px;color:#64748b">Falls der Knopf nicht funktioniert: {{ $url }}</p>
        @endif
        <p style="margin:24px 0 0;color:#475569">Viele Grüße<br>{{ $site }}</p>
    </div>
</div>
</body>
</html>
