@extends('exports.pdf.layout', ['title' => 'Credit Statement — ' . ($data['customer']['customer_name'] ?? 'Customer')])

@section('content')
@php
    $customer = $data['customer'];
    $txns     = $data['transactions'] ?? [];
    $balance  = $data['balance'] ?? 0;
@endphp

<table style="margin-bottom:14px;">
    <tr style="background:#f9fafb;">
        <td style="width:25%;"><strong>Customer:</strong></td>
        <td style="width:25%;">{{ $customer['customer_name'] }}</td>
        <td style="width:25%;"><strong>Period:</strong></td>
        <td style="width:25%;">{{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</td>
    </tr>
    <tr>
        <td><strong>Credit Limit:</strong></td>
        <td>KES {{ number_format($customer['credit_limit'] ?? 0) }}</td>
        <td><strong>Current Balance:</strong></td>
        <td style="font-weight:bold; color:#dc2626;">KES {{ number_format($balance) }}</td>
    </tr>
    @if(!empty($customer['phone']))
    <tr style="background:#f9fafb;">
        <td><strong>Phone:</strong></td>
        <td colspan="3">{{ $customer['phone'] }}</td>
    </tr>
    @endif
</table>

<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Product</th>
            <th class="num">Qty (L)</th>
            <th class="num">Amount</th>
            <th class="num">Balance</th>
        </tr>
    </thead>
    <tbody>
        @forelse($txns as $txn)
            <tr style="{{ $txn['type'] === 'payment' ? 'background:#f0fdf4;' : '' }}">
                <td>{{ \Carbon\Carbon::parse($txn['date'])->format('d M Y') }}</td>
                <td>
                    <span class="badge {{ $txn['type'] === 'payment' ? 'badge-green' : 'badge-red' }} capitalize">
                        {{ $txn['type'] }}
                    </span>
                </td>
                <td>{{ $txn['product'] ?? '—' }}</td>
                <td class="num">{{ !empty($txn['quantity']) ? number_format($txn['quantity'], 3) : '—' }}</td>
                <td class="num {{ $txn['type'] === 'payment' ? 'pos' : 'neg' }}">
                    {{ $txn['type'] === 'payment' ? '+' : '-' }} KES {{ number_format(abs($txn['amount'])) }}
                </td>
                <td class="num" style="font-weight:bold;">KES {{ number_format($txn['balance']) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center; padding:20px;" class="muted">No transactions in selected period.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
