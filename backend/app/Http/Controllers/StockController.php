<?php

namespace App\Http\Controllers;

use App\Models\ShopProduct;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Http\JsonResponse;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $station = $request->user()->station;
        $search  = trim($request->get('search', ''));
        $perPage = in_array((int) $request->get('per_page'), [10, 20, 50, 100])
            ? (int) $request->get('per_page')
            : 20;

        $products = ShopProduct::where('station_id', $station->id)
            ->when($search, fn($q) => $q->where('product_name', 'like', "%{$search}%"))
            ->with(['stockTransactions' => fn($q) => $q
                ->with('enteredBy:id,name')
                ->orderByDesc('trans_date')
                ->orderByDesc('id')
            ])
            ->orderByRaw('is_active DESC')
            ->orderBy('product_name')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Stock/Index', [
            'products' => $products,
            'filters'  => ['search' => $search, 'per_page' => $perPage],
        ]);
    }

    public function oilSalesHistory(Request $request, ShopProduct $shopProduct): JsonResponse
    {
        abort_if($shopProduct->station_id !== $request->user()->station->id, 403);

        $sales = $shopProduct->oilSales()
            ->with('shift:id,shift_date,dsr_number')
            ->with('enteredBy:id,name')
            ->orderByDesc('id')
            ->get(['id', 'shift_id', 'quantity', 'unit_price', 'total_value', 'entered_by']);

        return response()->json($sales);
    }
}
