@php
$colors = ['critical' => '#cf222e', 'warning' => '#9a6700', 'info' => '#6e7781', 'ok' => '#1a7f37'];
$labels = ['critical' => 'critical', 'warning' => 'warnings', 'info' => 'updates'];
$diskColor = fn (int $disk): string => $disk >= 90 ? $colors['critical'] : ($disk >= 75 ? '#d4a72c' : $colors['ok']);
@endphp
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Daily health report</title></head>
<body style="margin:0;background:#f5f5f5;color:#202020;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:14px;line-height:1.5">
<div style="max-width:680px;margin:16px auto;padding:20px 24px;background:#fff;border:1px solid #ddd;border-radius:10px">
<h1 style="font-size:20px;margin:0">Daily health report</h1>
<p style="color:#666;margin:2px 0 0">{{ $date }} · last 24 hours until 09:00 {{ config('app.timezone') }}</p>
@foreach ($groups as $group)
<div style="margin-top:18px;padding-top:14px;border-top:1px solid #ddd">
<h2 style="font-size:16px;margin:0 0 6px">{{ $group['name'] }}</h2>
<div style="font-size:13px;margin-bottom:6px">
@if ($group['checks'] === 0)
<span style="color:#666">No monitoring data for this period.</span>
@elseif (array_sum($group['counts']) === 0)
<span style="color:{{ $colors['ok'] }};font-weight:600;margin-right:12px">● All clear</span>
@else
@foreach ($group['counts'] as $level => $count)
@if ($count > 0)<span style="color:{{ $colors[$level] }};font-weight:600;margin-right:12px">● {{ $count }} {{ $labels[$level] }}</span>@endif
@endforeach
@endif
<span style="color:#999">{{ $group['checks'] }} hourly checks</span>
</div>
@if ($group['servers'] !== [])
<table style="width:100%;border-collapse:collapse">
@foreach ($group['servers'] as $name => $server)
<tr>
<td style="border-top:1px solid #eee;padding:8px 14px 8px 0;vertical-align:top;width:170px">
<a href="{{ $server['url'] }}" style="color:#202020;font-weight:600;text-decoration:none">{{ $name }}</a>
@if ($server['disk'] !== null)
<div style="margin-top:4px;width:110px;height:6px;background:#eee;border-radius:3px;overflow:hidden"><div style="width:{{ min($server['disk'], 100) }}%;height:6px;background:{{ $diskColor($server['disk']) }}"></div></div>
<div style="font-size:12px;color:{{ $server['disk'] >= 90 ? $colors['critical'] : ($server['disk'] >= 75 ? $colors['warning'] : '#666') }}">Disk {{ $server['disk'] }}%</div>
@else
<div style="font-size:12px;color:#999">Disk n/a</div>
@endif
</td>
<td style="border-top:1px solid #eee;padding:8px 0;vertical-align:top">
@forelse ($server['open'] as $issue)
<div><a href="{{ $issue['url'] }}" style="color:{{ $colors[$issue['level']] }};text-decoration:none">● {{ $issue['label'] }}</a></div>
@empty
<span style="color:{{ $colors['ok'] }}">✓ OK</span>
@endforelse
@if ($server['resolved'] !== [])
<div style="color:{{ $colors['ok'] }};font-size:12px;margin-top:2px">Resolved: {{ implode(' · ', $server['resolved']) }}</div>
@endif
</td>
</tr>
@endforeach
</table>
@endif
</div>
@endforeach
<p style="font-size:12px;color:#666;margin:16px 0 0;padding-top:12px;border-top:1px solid #ddd">
<span style="color:{{ $colors['critical'] }}">● critical</span> · <span style="color:{{ $colors['warning'] }}">● warning</span> · <span style="color:{{ $colors['info'] }}">● updates</span> — still present in the latest hourly check.<br>
Disk: latest measurement · <span style="color:{{ $colors['ok'] }}">&lt;75%</span> · <span style="color:{{ $colors['warning'] }}">75–89%</span> · <span style="color:{{ $colors['critical'] }}">≥90%</span>
</p>
</div></body></html>
