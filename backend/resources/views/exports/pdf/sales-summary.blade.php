@extends('exports.pdf.layout', ['title' => 'Sales Summary'])

@section('content')
@php
    $totals = [
        'litres'  => collect($rows)->sum('total_litres_sold'),
        'revenue' => collect($rows)->sum('total_revenue'),
        'cash'    => collect($rows)->sum('total_cash_sales'),
        'credit'  => collect($rows)->sum('total_credit_sales'),
    ];
@endphp

<div class="summary">
    <div class="summary-grid">
        <div class="summary-cell">
            <div class="label">Total Litres</div>
            <div class="value">{{ number_format($totals['litres'], 1) }} L</div>
        </div>
        <div class="summary-cell" style="border-left-color:#16a34a;">
            <div class="label">Total Revenue</div>
            <div class="value">KES {{ number_format($totals['revenue']) }}</div>
        </div>
        <div class="summary-cell" style="border-left-color:#2563eb;">
            <div class="label">Cash</div>
            <div class="value">KES {{ number_format($totals['cash']) }}</div>
        </div>
        <div class="summary-cell" style="border-left-color:#7c3aed;">
            <div class="label">Credit</div>
            <div class="value">KES {{ number_format($totals['credit']) }}</div>
        </div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>Shift</th>
            <th class="num">Litres</th>
            <th class="num">Revenue</th>
            <th class="num">Cash</th>
            <th class="num">Credit</th>
            <th class="num">Variance</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $r)
            <tr>
                <td>{{ \Carbon\Carbon::parse($r['shift_date'])->format('d M Y') }}</td>
                <td class="capitalize">{{ $r['shift_type'] }}</td>
                <td class="num">{{ number_format($r['total_litres_sold'], 1) }}</td>
                <td class="num">{{ number_format($r['total_revenue']) }}</td>
                <td class="num pos">{{ number_format($r['total_cash_sales']) }}</td>
                <td class="num" style="color:#1d4ed8;">{{ number_format($r['total_credit_sales']) }}</td>
                <td class="num {{ $r['variance'] < 0 ? 'neg' : 'pos' }}">{{ number_format($r['variance'], 1) }}</td>
                <td>
                    <span class="badge {{ $r['locked'] ? 'badge-purple' : 'badge-yellow' }}">
                        {{ $r['locked'] ? 'Locked' : 'Draft' }}
                    </span>
                </td>
            </tr>
        @empty
            <tr><td colspan="8" style="text-align:center; padding:20px;" class="muted">No data for selected period.</td></tr>
        @endforelse
        @if(count($rows))
            <tr class="totals-row">
                <td colspan="2">TOTAL</td>
                <td class="num">{{ number_format($totals['litres'], 1) }}</td>
                <td class="num">{{ number_format($totals['revenue']) }}</td>
                <td class="num">{{ number_format($totals['cash']) }}</td>
                <td class="num">{{ number_format($totals['credit']) }}</td>
                <td colspan="2"></td>
            </tr>
        @endif
    </tbody>
</table>
@endsection
