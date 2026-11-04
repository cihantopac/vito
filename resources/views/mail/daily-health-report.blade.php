@php
$sections = [
    'critical' => ['title' => 'Critical', 'color' => '#cf222e', 'bg' => '#fff5f5'],
    'warning' => ['title' => 'Warnings', 'color' => '#9a6700', 'bg' => '#fffbeb'],
];
$updates = $report['updates'];
$td = 'padding:5px 8px;border-top:1px solid #eee;vertical-align:top';
@endphp
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Daily health report</title></head>
<body style="margin:0;background:#f5f5f5;color:#202020;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:13px;line-height:1.45">
<div style="max-width:680px;margin:16px auto;padding:18px 22px;background:#fff;border:1px solid #ddd;border-radius:10px">
<h1 style="font-size:19px;margin:0">Daily health report</h1>
<p style="color:#666;margin:2px 0 10px">{{ $date }} · status at 09:00 {{ config('app.timezone') }} · {{ $report['projects'] }} projects · {{ $report['servers'] }} servers</p>
<p style="margin:0 0 12px;font-weight:600">
<span style="color:#cf222e">● {{ count($report['critical']) }} servers critical</span>&nbsp;&nbsp;
<span style="color:#9a6700">● {{ count($report['warning']) }} with warnings</span>&nbsp;&nbsp;
<span style="color:#6e7781">● {{ $updates['servers'] }} need updates</span>
</p>
@foreach ($sections as $level => $section)
@if ($report[$level] !== [])
<table style="width:100%;border-collapse:collapse;margin-bottom:12px;background:{{ $section['bg'] }};border-left:3px solid {{ $section['color'] }}">
<tr><td colspan="2" style="padding:6px 8px;color:{{ $section['color'] }};font-weight:700">{{ $section['title'] }}</td></tr>
@foreach ($report[$level] as $row)
<tr>
<td style="{{ $td }};white-space:nowrap"><a href="{{ $row['url'] }}" style="color:#202020;font-weight:600;text-decoration:none">{{ $row['server'] }}</a> <span style="color:#888">{{ $row['project'] }}</span></td>
<td style="{{ $td }};color:{{ $section['color'] }}">{{ implode(', ', $row['issues']) }}</td>
</tr>
@endforeach
</table>
@endif
@endforeach
@if ($updates['servers'] > 0)
<p style="margin:0 0 8px;color:#555"><strong>Updates:</strong> {{ $updates['packages'] }} packages{{ $updates['kernel'] > 0 ? ' and '.$updates['kernel'].' kernel updates' : '' }} pending on {{ $updates['servers'] }} servers. Most:
@foreach ($updates['top'] as $top)<a href="{{ $top['url'] }}" style="color:#555">{{ $top['server'] }}</a> {{ $top['count'] }}{{ $loop->last ? '.' : ', ' }}@endforeach
</p>
@endif
@if ($report['critical'] === [] && $report['warning'] === [])
<p style="margin:0 0 8px;color:#1a7f37;font-weight:600">✓ No critical issues or warnings.</p>
@endif
<p style="font-size:12px;color:#666;margin:12px 0 0;padding-top:10px;border-top:1px solid #ddd">
@if ($report['clear'] !== [])<span style="color:#1a7f37">✓ All clear:</span> {{ implode(', ', $report['clear']) }}.@endif
@if ($report['missing'] !== []) No monitoring data: {{ implode(', ', $report['missing']) }}.@endif
Disk warning at 75%, critical above 90%.
</p>
</div></body></html>
