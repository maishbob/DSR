@extends('exports.pdf.layout', ['title' => 'Wet Stock Reconciliation'])

@section('content')
<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>Shift</th>
            <th>Product</th>
            <th>Tank</th>
            <th class="num">Opening</th>
            <th class="num">+ Deliv.</th>
            <th class="num">- Sold</th>
            <th class="num">= Expected</th>
            <th class="num">Actual Dip</th>
            <th class="num">Variance</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $row)
            <tr>
                <td>{{ \Carbon\Carbon::parse($row['date'])->format('d M Y') }}</td>
                <td class="capitalize">{{ $row['shift_type'] }}</td>
                <td>{{ $row['product'] }}</td>
                <td>{{ $row['tank'] ?? '—' }}</td>
                <td class="num">{{ number_format($row['opening_stock'], 1) }}</td>
                <td class="num">{{ number_format($row['deliveries'], 1) }}</td>
                <td class="num">{{ number_format($row['litres_sold'], 1) }}</td>
                <td class="num">{{ number_format($row['expected_stock'], 1) }}</td>
                <td class="num">{{ number_format($row['actual_stock'], 1) }}</td>
                <td class="num {{ $row['variance'] < 0 ? 'neg' : ($row['variance'] > 0 ? 'pos' : 'muted') }}">
                    {{ number_format($row['variance'], 1) }}
                </td>
            </tr>
        @empty
            <tr><td colspan="10" style="text-align:center; padding:20px;" class="muted">No data for selected period.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
