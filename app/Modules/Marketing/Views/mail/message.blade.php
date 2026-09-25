<!DOCTYPE html>
<html lang="en">
<body style="font-family:system-ui,sans-serif;color:#111;max-width:560px;margin:24px auto;line-height:1.5;">
    <p style="font-weight:600;font-size:18px;margin:0 0 16px;">{{ $business }}</p>
    <p>{!! nl2br(e($text)) !!}</p>
    @if ($unsubscribeUrl)
        <p style="color:#777;font-size:12px;margin-top:32px;">You are receiving this because you agreed to hear from {{ $business }}. <a href="{{ $unsubscribeUrl }}">Unsubscribe</a>.</p>
    @endif
</body>
</html>
