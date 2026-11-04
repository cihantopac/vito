<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Daily health report</title></head>
<body style="margin:0;background:#f5f5f5;color:#202020;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:14px;line-height:1.5">
<div style="max-width:640px;margin:16px auto;padding:20px 24px;background:#fff;border:1px solid #ddd;border-radius:10px">
<h1 style="font-size:20px;margin:0">Daily health report</h1>
<p style="color:#666;margin:2px 0 0">{{ $date }} · last 24 hours until 09:00 {{ config('app.timezone') }}</p>
@foreach ($groups as $group)
<h2 style="font-size:16px;margin:18px 0 4px;padding-top:14px;border-top:1px solid #ddd">{{ $group['name'] }}
<span style="font-weight:normal;color:#666;font-size:13px">· {{ $group['open'] }} open · {{ $group['checks'] }} hourly checks</span></h2>
@if ($group['checks'] === 0)
<p style="margin:0;color:#666">No monitoring data for this period.</p>
@elseif ($group['servers'] === [])
<p style="margin:0">All clear.</p>
@else
<table style="width:100%;border-collapse:collapse">
@foreach ($group['servers'] as $name => $server)
<tr>
<td style="border-top:1px solid #eee;padding:6px 12px 6px 0;vertical-align:top;white-space:nowrap"><a href="{{ $server['url'] }}" style="color:#202020;font-weight:600">{{ $name }}</a></td>
<td style="border-top:1px solid #eee;padding:6px 0">
@if ($server['open'] === [])
<span style="color:#1a7f37">OK</span>
@else
@foreach ($server['open'] as $issue)<a href="{{ $issue['url'] }}" style="color:#202020">{{ $issue['label'] }}</a>@if (! $loop->last)<span style="color:#999"> · </span>@endif @endforeach
@endif
@if ($server['resolved'] !== [])
<div style="color:#1a7f37;font-size:12px">Resolved: {{ implode(' · ', $server['resolved']) }}</div>
@endif
</td>
</tr>
@endforeach
</table>
@endif
@endforeach
<p style="font-size:12px;color:#666;margin:16px 0 0;padding-top:12px;border-top:1px solid #ddd">Open = still present in the latest hourly check. Short incidents between checks may not appear.</p>
</div></body></html>
