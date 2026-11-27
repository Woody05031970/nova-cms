<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>{{ $c->subject }}</title></head>
<body style="margin:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a">
@if ($c->preheader)<div style="display:none;max-height:0;overflow:hidden">{{ $c->preheader }}</div>@endif
<div style="max-width:620px;margin:0 auto;padding:24px 12px">
    <div style="padding:0 8px 16px">
        @if ($logo)<img src="{{ $logo }}" alt="{{ $site }}" style="height:36px;width:auto">@else<strong style="font-size:18px">{{ $site }}</strong>@endif
    </div>
    <div style="background:#fff;border-radius:16px;overflow:hidden;border:1px solid #e2e8f0">
        <div style="height:6px;background:{{ $brand }}"></div>
        @if ($c->image)<img src="{{ str_starts_with($c->image, 'http') ? $c->image : url($c->image) }}" alt="" style="display:block;width:100%;height:auto">@endif
        <div style="padding:28px">
            @if ($c->heading)<h1 style="font-size:24px;line-height:1.25;margin:0 0 16px">{{ $c->heading }}</h1>@endif
            @foreach ($c->paragraphs() as $p)
                <p style="font-size:16px;line-height:1.6;margin:0 0 14px">{!! nl2br(e($p)) !!}</p>
            @endforeach
            @if ($c->button_label && $c->button_url)
                <p style="margin:24px 0 4px"><a href="{{ $c->button_url }}" style="background:{{ $brand }};color:#fff;text-decoration:none;padding:13px 22px;border-radius:10px;font-weight:bold;display:inline-block">{{ $c->button_label }}</a></p>
            @endif
        </div>
    </div>
    <p style="font-size:12px;color:#64748b;text-align:center;line-height:1.6;margin:18px 8px">
        Sie erhalten diese E-Mail, weil Sie den Newsletter von {{ $site }} abonniert haben.<br>
        <a href="{{ $unsubscribe }}" style="color:#64748b">Newsletter abbestellen</a>@if ($address) · {{ $address }}@endif
    </p>
</div>
</body></html>
