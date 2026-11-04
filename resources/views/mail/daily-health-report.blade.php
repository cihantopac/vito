@php
$colors = ['critical' => '#cf222e', 'warning' => '#9a6700', 'info' => '#6e7781', 'ok' => '#1a7f37'];
$diskColor = fn (int $disk): string => $disk >= 90 ? $colors['critical'] : ($disk >= 75 ? '#d4a72c' : $colors['ok']);
$cell = 'border-top:1px solid #eee;padding:6px 0;vertical-align:top';
$nameCell = 'border-top:1px solid #eee;padding:6px 14px 6px 0;vertical-align:top;width:170px;font-weight:600';
@endphp
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Daily health report</title></head>
<body style="margin:0;background:#f5f5f5;color:#202020;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:14px;line-height:1.5">
<div style="max-width:680px;margin:16px auto;padding:20px 24px;background:#fff;border:1px solid #ddd;border-radius:10px">
<h1 style="font-size:20px;margin:0">Daily health report</h1>
<p style="color:#666;margin:2px 0 0">{{ $date }} · status at 09:00 {{ config('app.timezone') }}</p>
@foreach ($groups as $group)
<div style="margin-top:18px;padding-top:14px;border-top:1px solid #ddd">
<h2 style="font-size:16px;margin:0 0 8px">{{ $group['name'] }}</h2>
@if ($group['checks'] === 0)
<p style="margin:0;color:#666">No monitoring data for this period.</p>
@else
@if ($group['critical'] === [] && $group['warning'] === [])
<p style="margin:0 0 8px;color:{{ $colors['ok'] }};font-weight:600">✓ All clear</p>
@endif
@if ($group['critical'] !== [])
<div style="background:#fff5f5;border-left:3px solid {{ $colors['critical'] }};padding:8px 12px;margin-bottom:12px">
<div style="color:{{ $colors['critical'] }};font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:.03em">Critical · {{ array_sum(array_map('count', $group['critical'])) }}</div>
<table style="width:100%;border-collapse:collapse">
@foreach ($group['critical'] as $server => $items)
<tr><td style="{{ $nameCell }}">{{ $server }}</td><td style="{{ $cell }}">
@foreach ($items as $issue)<div><a href="{{ $issue['url'] }}" style="color:{{ $colors['critical'] }}">{{ $issue['label'] }}</a></div>@endforeach
</td></tr>
@endforeach
</table>
</div>
@endif
@if ($group['warning'] !== [])
<div style="color:{{ $colors['warning'] }};font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:.03em">Warnings</div>
<table style="width:100%;border-collapse:collapse;margin-bottom:12px;font-size:13px">
@foreach ($group['warning'] as $server => $items)
<tr><td style="{{ $nameCell }};font-weight:normal">{{ $server }}</td><td style="{{ $cell }}">
@foreach ($items as $issue)<div><a href="{{ $issue['url'] }}" style="color:{{ $colors[$issue['level']] }};text-decoration:none">{{ $issue['label'] }}</a></div>@endforeach
</td></tr>
@endforeach
</table>
@endif
@endif
@if ($group['disks'] !== [])
<div style="color:#666;font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:.03em">Disk usage</div>
<table style="width:100%;border-collapse:collapse;font-size:13px">
@foreach ($group['disks'] as $server => $row)
<tr>
<td style="padding:3px 14px 3px 0;width:170px"><a href="{{ $row['url'] }}" style="color:#202020;text-decoration:none">{{ $server }}</a></td>
@if ($row['disk'] !== null)
<td style="padding:3px 0"><div style="width:100%;height:6px;background:#eee;border-radius:3px;overflow:hidden"><div style="width:{{ min($row['disk'], 100) }}%;height:6px;background:{{ $diskColor($row['disk']) }}"></div></div></td>
<td align="right" style="padding:3px 0 3px 10px;width:44px;color:{{ $row['disk'] >= 90 ? $colors['critical'] : ($row['disk'] >= 75 ? $colors['warning'] : '#666') }};font-weight:{{ $row['disk'] >= 75 ? 600 : 400 }}">{{ $row['disk'] }}%</td>
@else
<td style="padding:3px 0;color:#999">n/a</td><td></td>
@endif
</tr>
@endforeach
</table>
@endif
</div>
@endforeach
<p style="font-size:12px;color:#666;margin:16px 0 0;padding-top:12px;border-top:1px solid #ddd">Status from the latest hourly check. Disk: <span style="color:{{ $colors['ok'] }}">&lt;75%</span> · <span style="color:{{ $colors['warning'] }}">75–89%</span> · <span style="color:{{ $colors['critical'] }}">≥90%</span></p>
</div></body></html>
