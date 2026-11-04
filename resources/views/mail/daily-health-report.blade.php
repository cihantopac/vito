@php
$sections = [
    'critical' => ['title' => 'Critical', 'hint' => 'needs action now', 'color' => '#cf222e', 'bg' => '#fff5f5'],
    'warning' => ['title' => 'Warnings', 'hint' => 'keep an eye on', 'color' => '#9a6700', 'bg' => '#fffbeb'],
];
$updates = $report['updates'];
$stats = [
    ['count' => count($report['critical']), 'label' => 'critical servers', 'color' => '#cf222e'],
    ['count' => count($report['warning']), 'label' => 'with warnings', 'color' => '#9a6700'],
    ['count' => $updates['servers'], 'label' => 'need updates', 'color' => '#6e7781'],
];
$td = 'padding:7px 10px;border-top:1px solid rgba(0,0,0,.06);vertical-align:top';
@endphp
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Daily health report</title></head>
<body style="margin:0;background:#f5f5f5;color:#202020;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:13px;line-height:1.45">
<div style="max-width:680px;margin:16px auto;padding:20px 22px;background:#fff;border:1px solid #ddd;border-radius:10px">
<h1 style="font-size:19px;margin:0">Daily health report</h1>
<p style="color:#666;margin:2px 0 14px">{{ $date }} · status at 09:00 {{ config('app.timezone') }} · {{ $report['projects'] }} projects · {{ $report['servers'] }} servers</p>
<table style="width:100%;border-collapse:separate;border-spacing:6px 0;margin:0 -6px 16px"><tr>
@foreach ($stats as $stat)
<td style="width:33%;padding:10px 12px;border:1px solid #eee;border-radius:8px;border-top:3px solid {{ $stat['count'] > 0 ? $stat['color'] : '#1a7f37' }}">
<div style="font-size:24px;font-weight:700;line-height:1.1;color:{{ $stat['count'] > 0 ? $stat['color'] : '#1a7f37' }}">{{ $stat['count'] }}</div>
<div style="color:#666;font-size:12px">{{ $stat['label'] }}</div>
</td>
@endforeach
</tr></table>
@foreach ($sections as $level => $section)
@if ($report[$level] !== [])
<table style="width:100%;border-collapse:collapse;margin-bottom:14px;background:{{ $section['bg'] }};border-left:3px solid {{ $section['color'] }}">
<tr><td colspan="2" style="padding:7px 10px;color:{{ $section['color'] }};font-weight:700">{{ $section['title'] }} <span style="font-weight:normal;color:#888">· {{ $section['hint'] }}</span></td></tr>
@foreach ($report[$level] as $row)
<tr>
<td style="{{ $td }};white-space:nowrap;width:1%"><a href="{{ $row['url'] }}" style="color:#202020;font-weight:600;text-decoration:underline;text-decoration-color:#ccc">{{ $row['server'] }}</a> <span style="color:#999;font-size:12px">{{ $row['project'] }}</span></td>
<td style="{{ $td }};color:{{ $section['color'] }}">
@foreach ($row['issues'] as $status => $names)
<div>@if ($status !== '')<strong>{{ $status }}:</strong> @endif{{ implode(', ', $names) }}</div>
@endforeach
</td>
</tr>
@endforeach
</table>
@endif
@endforeach
@if ($report['critical'] === [] && $report['warning'] === [])
<p style="margin:0 0 14px;padding:10px 12px;background:#f0fff4;border-left:3px solid #1a7f37;color:#1a7f37;font-weight:600">✓ No critical issues or warnings across your servers.</p>
@endif
@if ($updates['servers'] > 0)
<p style="margin:0 0 6px;color:#555"><strong style="color:#202020">Pending updates</strong> · {{ $updates['packages'] }} packages{{ $updates['kernel'] > 0 ? ' and '.$updates['kernel'].' kernel' : '' }} on {{ $updates['servers'] }} servers</p>
<p style="margin:0 0 6px;color:#555">Largest:
@foreach ($updates['top'] as $top)<a href="{{ $top['url'] }}" style="color:#555">{{ $top['server'] }}</a> ({{ $top['count'] }}){{ $loop->last ? '' : ' · ' }}@endforeach
</p>
@endif
<p style="font-size:12px;color:#666;margin:14px 0 0;padding-top:10px;border-top:1px solid #ddd">
@if ($report['clear'] !== [])<span style="color:#1a7f37">✓ All clear:</span> {{ implode(', ', $report['clear']) }}<br>@endif
@if ($report['missing'] !== [])No monitoring data: {{ implode(', ', $report['missing']) }}<br>@endif
Disk is a warning from 75% and critical above 90%. Click a server to open it.
</p>
</div></body></html>
