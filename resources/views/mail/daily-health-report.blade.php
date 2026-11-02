<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Pure daily health report</title></head>
<body style="margin:0;background:#f5f5f5;color:#202020;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;line-height:1.6">
<div style="max-width:640px;margin:24px auto;padding:28px;background:#fff;border:1px solid #ddd;border-radius:12px">
<h1 style="font-size:24px;margin:0">Daily health report</h1>
<p style="color:#666">{{ $date }} · 09:00 · {{ config('app.timezone') }}<br>Previous 24 hours. Each recipient receives one report across their authorized projects.</p>
@foreach ($groups as $group)
<h2 style="font-size:19px;border-top:1px solid #ddd;padding-top:20px">{{ $group['name'] }}</h2>
@if (count($group['hours']) === 0)
<p>No monitoring snapshots are available for this period.</p>
@elseif (count($group['issues']) === 0)
<p>No issues detected in {{ count($group['hours']) }} hourly observations.</p>
@else
<p>{{ count($group['issues']) }} distinct items need attention.</p>
<ul style="padding-left:20px">
@foreach ($group['issues'] as $issue)
<li style="padding:5px 0"><a href="{{ $issue['url'] }}" style="color:#202020;text-decoration:underline">{{ $issue['label'] }}</a></li>
@endforeach
</ul>
@endif
@if (count($group['hours']) > 0)
<h3 style="font-size:15px">Hourly summary</h3>
<table style="width:100%;border-collapse:collapse;font-size:14px">
<thead><tr><th align="left">Observation ({{ config('app.timezone') }})</th><th align="right">Items requiring attention</th></tr></thead>
<tbody>
@foreach ($group['hours'] as $hour)
<tr><td style="border-top:1px solid #eee;padding:6px 0">{{ $hour->hour->format('d M H:i') }}</td><td align="right" style="border-top:1px solid #eee">{{ count($hour->issues) }}</td></tr>
@endforeach
</tbody></table>
@endif
@endforeach
<p style="font-size:12px;color:#666;border-top:1px solid #ddd;padding-top:16px">RAM and disk percentages are server measurements. Listed sites share the affected server. Hourly observations may not capture short-lived incidents between samples.</p>
</div></body></html>
