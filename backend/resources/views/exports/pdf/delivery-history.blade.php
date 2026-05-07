@extends('exports.pdf.layout', ['title' => 'Delivery History'])

@section('content')
<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>Product</th>
            <th>Tank</th>
            <th>Supplier</th>
            <th>Waybill</th>
            <th class="num">Qty (L)</th>
            <th class="num">Dip Before</th>
            <th class="num">Dip After</th>
            <th class="num">Variance</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $r)
            <tr>
                <td>{{ \Carbon\Carbon::parse($r['delivery_date'])->format('d M Y') }}</td>
                <td>{{ $r['product']['product_name'] ?? '—' }}</td>
                <td>{{ $r['tank']['tank_name'] ?? '—' }}</td>
                <td>{{ $r['supplier_name'] ?? '—' }}</td>
                <td>{{ $r['waybill_number'] ?? '—' }}</td>
                <td class="num">{{ number_format($r['delivery_quantity'], 1) }}</td>
                <td class="num">{{ $r['tank_dip_before'] !== null ? number_format($r['tank_dip_before'], 1) : '—' }}</td>
                <td class="num">{{ $r['tank_dip_after']  !== null ? number_format($r['tank_dip_after'],  1) : '—' }}</td>
                <td class="num {{ $r['delivery_variance'] === null ? 'muted' : ($r['delivery_variance'] < 0 ? 'neg' : 'pos') }}">
                    {{ $r['delivery_variance'] !== null ? number_format($r['delivery_variance'], 1) : '—' }}
                </td>
            </tr>
        @empty
            <tr><td colspan="9" style="text-align:center; padding:20px;" class="muted">No deliveries in selected period.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
