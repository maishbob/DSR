<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 30px 25px; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #1f2937; }

        .header { border-bottom: 2px solid #f97316; padding-bottom: 8px; margin-bottom: 14px; }
        .header h1 { margin: 0 0 2px 0; font-size: 16px; color: #111827; }
        .header .meta { font-size: 9px; color: #6b7280; }
        .header .station { font-weight: bold; font-size: 11px; color: #374151; }

        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th { background: #f3f4f6; text-align: left; padding: 5px 6px; font-size: 9px; color: #374151; border-bottom: 1px solid #d1d5db; text-transform: uppercase; letter-spacing: 0.3px; }
        td { padding: 4px 6px; border-bottom: 1px solid #f3f4f6; font-size: 9.5px; }
        tr:nth-child(even) td { background: #fafafa; }

        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .neg { color: #dc2626; }
        .pos { color: #16a34a; }
        .muted { color: #9ca3af; }
        .capitalize { text-transform: capitalize; }

        .totals-row td { border-top: 2px solid #374151; font-weight: bold; background: #f9fafb !important; padding-top: 6px; }

        .footer { position: fixed; bottom: -20px; left: 0; right: 0; font-size: 8px; color: #9ca3af; text-align: center; }
        .page-num:before { content: "Page " counter(page); }

        .badge { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 8px; font-weight: bold; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-orange { background: #ffedd5; color: #9a3412; }
        .badge-yellow { background: #fef3c7; color: #92400e; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-purple { background: #f3e8ff; color: #6b21a8; }

        .summary { margin: 8px 0 12px 0; }
        .summary-grid { display: table; width: 100%; }
        .summary-cell { display: table-cell; padding: 6px 10px; background: #f9fafb; border-left: 3px solid #f97316; }
        .summary-cell .label { font-size: 8px; color: #6b7280; text-transform: uppercase; }
        .summary-cell .value { font-size: 12px; font-weight: bold; color: #111827; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title }}</h1>
        @if(!empty($station))
            <div class="station">{{ $station->station_name }}</div>
        @endif
        <div class="meta">
            @if(!empty($from) && !empty($to))
                Period: {{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}
                &nbsp;·&nbsp;
            @endif
            Generated: {{ now()->format('d M Y H:i') }}
        </div>
    </div>

    @yield('content')

    <div class="footer">
        <span class="page-num"></span>
    </div>
</body>
</html>
