<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"></head>
<body style="margin:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a">
<div style="max-width:640px;margin:0 auto;padding:24px">
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px">
        <p style="margin:0 0 4px;font-size:12px;color:#64748b">{{ $title }} · {{ $site }}</p>
        <p style="margin:0 0 12px">Hallo {{ $user->name }},</p>
        <p style="margin:0 0 12px">{{ $text }}</p>
        <p style="margin:20px 0"><a href="{{ $url }}" style="display:inline-block;background:#4f46e5;color:#fff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:bold">Ansehen</a></p>
        <p style="margin:0;font-size:12px;color:#64748b">Diese Hinweise per E-Mail schalten Sie in Ihrem Profil unter „Profil bearbeiten“ ab.</p>
    </div>
</div>
</body>
</html>
