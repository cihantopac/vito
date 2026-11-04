@php
    $colors = ['critical' => '#ff3b30', 'warning' => '#ff9500'];
    $counts = $report['counts'];
    $problems = $counts['critical'] + $counts['warning'];
    $summary = collect(['critical', 'warning'])->filter(fn (string $severity): bool => $counts[$severity] > 0)->map(fn (string $severity): string => $counts[$severity].' '.$severity)->implode(', ');
    $muted = 'color:#86868b;font-size:13px';
    $row = 'border-top:1px solid #e5e5ea;padding:14px 0;vertical-align:top';
@endphp
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Daily health report</title></head>
<body style="margin:0;background:#f5f5f7;color:#1d1d1f;font-family:-apple-system,BlinkMacSystemFont,'SF Pro Text','Helvetica Neue',sans-serif;font-size:15px;line-height:1.4;-webkit-font-smoothing:antialiased">
<div style="max-width:600px;margin:24px auto;padding:36px 32px;background:#fff;border-radius:18px">
<p style="margin:0;{{ $muted }}">Daily health report · {{ \Illuminate\Support\Carbon::parse($date)->format('j M Y') }}</p>
<h1 style="margin:6px 0 4px;font-size:28px;font-weight:600;letter-spacing:-0.02em">{{ $problems === 0 ? 'Everything is running' : $problems.' '.Str::plural('issue', $problems).' need attention' }}</h1>
<p style="margin:0 0 24px;{{ $muted }}">
{{ $summary !== '' ? $summary.' · ' : '' }}{{ $counts['info'] > 0 ? $counts['info'].' '.Str::plural('server', $counts['info']).' with updates' : 'No pending updates' }}
</p>

@if (count($report['open']) > 0)
<table role="presentation" style="width:100%;border-collapse:collapse">
@foreach ($report['open'] as $item)
<tr>
<td style="{{ $row }};width:16px"><span style="display:inline-block;width:8px;height:8px;margin-top:6px;border-radius:50%;background:{{ $colors[$item['severity']] ?? $colors['warning'] }}"></span></td>
<td style="{{ $row }}">
<a href="{{ $item['url'] }}" style="color:#1d1d1f;text-decoration:none"><strong style="font-weight:600">{{ $item['server'] }}</strong> {{ $item['problem'] }}</a>
<div style="{{ $muted }};margin-top:2px">{{ $item['hint'] ? $item['hint'].' · ' : '' }}{{ $item['project'] }} · {{ $item['checks'] >= $item['total_checks'] ? 'all day' : 'since '.$item['since']->format('H:i') }}</div>
</td>
</tr>
@endforeach
</table>
@endif

@if (count($report['updates']) > 0)
<p style="margin:28px 0 6px;font-size:13px;font-weight:600;color:#86868b;text-transform:uppercase;letter-spacing:0.04em">Updates</p>
<table role="presentation" style="width:100%;border-collapse:collapse">
@foreach ($report['updates'] as $item)
<tr>
<td style="border-top:1px solid #e5e5ea;padding:10px 0"><a href="{{ $item['url'] }}" style="color:#1d1d1f;text-decoration:none">{{ $item['server'] }}</a></td>
<td align="right" style="border-top:1px solid #e5e5ea;padding:10px 0;{{ $muted }}">{{ $item['problem'] }}</td>
</tr>
@endforeach
</table>
@endif

@if (count($report['resolved']) > 0)
<p style="margin:28px 0 6px;font-size:13px;font-weight:600;color:#86868b;text-transform:uppercase;letter-spacing:0.04em">Resolved</p>
<p style="margin:0;{{ $muted }}">
@foreach (array_slice($report['resolved'], 0, 5) as $item)
{{ $item['server'] }} {{ $item['problem'] }}@if (! $loop->last)<br>@endif
@endforeach
@if (count($report['resolved']) > 5)<br>+{{ count($report['resolved']) - 5 }} more @endif
</p>
@endif

<p style="margin:32px 0 0;font-size:12px;color:#86868b">
@if (count($report['healthy']) > 0)Healthy: {{ implode(', ', $report['healthy']) }}<br>@endif
@if (count($report['no_data']) > 0)No data: {{ implode(', ', $report['no_data']) }}<br>@endif
Checked hourly · {{ config('app.timezone') }}
</p>
</div></body></html>
