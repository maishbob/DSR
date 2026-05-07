@extends('exports.pdf.layout', ['title' => 'Variance Report'])

@section('content')
<p style="font-size:9px; color:#6b7280; margin: 4px 0 8px 0;">{{ count($rows) }} record(s) with stock variance.</p>

<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>Shift</th>
            <th>Product</th>
            <th class="num">Expected</th>
            <th class="num">Actual</th>
            <th class="num">Variance (L)</th>
            <th>Severity</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $r)
            @php
                $abs = abs($r['variance']);
                $sev = $abs > 100 ? ['High', 'badge-red'] : ($abs > 50 ? ['Medium', 'badge-orange'] : ['Low', 'badge-yellow']);
            @endphp
            <tr>
                <td>{{ \Carbon\Carbon::parse($r['date'])->format('d M Y') }}</td>
                <td class="capitalize">{{ $r['shift_type'] }}</td>
                <td>{{ $r['product'] }}</td>
                <td class="num">{{ number_format($r['expected_stock'], 1) }}</td>
                <td class="num">{{ number_format($r['actual_stock'], 1) }}</td>
                <td class="num {{ $r['variance'] < 0 ? 'neg' : 'pos' }}" style="font-weight:bold;">
                    {{ number_format($r['variance'], 1) }}
                </td>
                <td><span class="badge {{ $sev[1] }}">{{ $sev[0] }}</span></td>
            </tr>
        @empty
            <tr><td colspan="7" style="text-align:center; padding:20px;" class="muted">No variances detected.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
