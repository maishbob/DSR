<?php

namespace App\Http\Controllers;

use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\PumpNozzle;
use App\Models\ShopProduct;
use App\Models\Station;
use App\Models\Tank;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class StationController extends Controller
{
    public function show(Request $request)
    {
        $station = $request->user()->station->load([
            'owner',
            'products.priceHistories' => fn($q) => $q->orderByDesc('effective_from'),
            'products.tanks',
            'tanks.product',
            'pumpNozzles.product',
            'pumpNozzles.tank',
            'pumpNozzles.latestReading',
        ]);

        $shopSearch  = trim($request->get('shop_search', ''));
        $shopPerPage = in_array((int) $request->get('shop_per_page'), [10, 20, 50, 100])
            ? (int) $request->get('shop_per_page')
            : 20;

        $shopProducts = ShopProduct::where('station_id', $station->id)
            ->when($shopSearch, fn($q) => $q->where('product_name', 'like', "%{$shopSearch}%"))
            ->with(['stockTransactions' => fn($q) => $q
                ->with('enteredBy:id,name')
                ->orderByDesc('trans_date')
                ->orderByDesc('id')
                ->limit(100),
            ])
            ->orderByRaw('is_active DESC')
            ->orderBy('product_name')
            ->paginate($shopPerPage)
            ->withQueryString();

        return Inertia::render('Station/Settings', [
            'station'      => $station,
            'shopProducts' => $shopProducts,
            'shopFilters'  => ['search' => $shopSearch, 'per_page' => $shopPerPage],
        ]);
    }

    // General settings
    public function updateRates(Request $request)
    {
        $validated = $request->validate([
            'vat_rate' => 'required|numeric|min:0|max:100',
            'wht_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $request->user()->station->update([
            'vat_rate' => $validated['vat_rate'] / 100,
            'wht_rate' => isset($validated['wht_rate']) ? $validated['wht_rate'] / 100 : null,
        ]);

        return back()->with('success', 'Tax rates updated.');
    }

    public function updatePeriod(Request $request)
    {
        $validated = $request->validate([
            'period_from' => 'required|date',
            'period_to'   => 'required|date|after_or_equal:period_from',
        ]);

        $request->user()->station->update($validated);

        return back()->with('success', 'Billing period updated.');
    }

    // Products
    public function storeProduct(Request $request)
    {
        $validated = $request->validate([
            'product_name'   => 'required|string|max:100',
            'cost_per_litre' => 'nullable|numeric|min:0',
        ]);

        $station = $request->user()->station;
        Product::create(array_merge($validated, ['station_id' => $station->id]));

        return back()->with('success', 'Product added.');
    }

    public function updateProduct(Request $request, Product $product)
    {
        $validated = $request->validate([
            'product_name'   => 'required|string|max:100',
            'cost_per_litre' => 'nullable|numeric|min:0',
            'is_active'      => 'boolean',
        ]);

        $product->update($validated);

        return back()->with('success', 'Product updated.');
    }

    // Tanks
    public function storeTank(Request $request)
    {
        $validated = $request->validate([
            'product_id'    => 'required|exists:products,id',
            'tank_name'     => 'required|string|max:100',
            'tank_capacity' => 'required|numeric|min:1',
            'is_complex'         => 'boolean',
            'last_closing_stock' => 'nullable|numeric|min:0',
            'last_dip_stock'     => 'nullable|numeric|min:0',
            'last_dip_2'         => 'nullable|numeric|min:0',
        ]);

        $station = $request->user()->station;
        Tank::create(array_merge($validated, ['station_id' => $station->id]));

        return back()->with('success', 'Tank added.');
    }

    public function updateTank(Request $request, Tank $tank)
    {
        $validated = $request->validate([
            'tank_name'          => 'required|string|max:100',
            'tank_capacity'      => 'required|numeric|min:1',
            'linked_tank_id'     => ['nullable', Rule::exists('tanks', 'id')->where('station_id', $tank->station_id)],
            'is_active'          => 'boolean',
            'is_complex'         => 'boolean',
            'last_closing_stock' => 'nullable|numeric|min:0',
            'last_dip_stock'     => 'nullable|numeric|min:0',
            'last_dip_2'         => 'nullable|numeric|min:0',
        ]);

        $tank->update($validated);

        return back()->with('success', 'Tank updated.');
    }

    // Pump Nozzles
    public function storeNozzle(Request $request)
    {
        $validated = $request->validate([
            'product_id'  => 'required|exists:products,id',
            'tank_id'     => 'nullable|exists:tanks,id',
            'nozzle_ref'  => 'nullable|string|max:15',
            'nozzle_name' => 'required|string|max:50',
            'main_pump'   => 'nullable|integer|min:1|max:99',
            'nozzle_no'   => 'nullable|integer|min:1|max:99',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        $station = $request->user()->station;
        PumpNozzle::create(array_merge($validated, ['station_id' => $station->id]));

        return back()->with('success', 'Nozzle added.');
    }

    public function updateNozzle(Request $request, PumpNozzle $nozzle)
    {
        $validated = $request->validate([
            'tank_id'     => 'nullable|exists:tanks,id',
            'nozzle_ref'  => 'nullable|string|max:15',
            'nozzle_name' => 'required|string|max:50',
            'main_pump'   => 'nullable|integer|min:1|max:99',
            'nozzle_no'   => 'nullable|integer|min:1|max:99',
            'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'boolean',
            'last_mech'   => 'nullable|numeric|min:0',
            'last_elec'   => 'nullable|numeric|min:0',
            'last_shs'    => 'nullable|numeric|min:0',
        ]);
        $nozzle->update($validated);
        return back()->with('success', 'Nozzle updated.');
    }

    public function destroyNozzle(PumpNozzle $nozzle)
    {
        $nozzle->delete();
        return back()->with('success', 'Nozzle removed.');
    }

    // Shop Products
    public function storeShopProduct(Request $request)
    {
        $validated = $request->validate([
            'product_name'    => 'required|string|max:100',
            'unit'            => 'required|string|max:20',
            'current_price'   => 'required|numeric|min:0',
            'cost'            => 'nullable|numeric|min:0',
            'forecourt_stock' => 'nullable|numeric|min:0',
            'store_stock'     => 'nullable|numeric|min:0',
        ]);

        $station = $request->user()->station;
        ShopProduct::create(array_merge($validated, ['station_id' => $station->id]));

        return back()->with('success', 'Shop product added.');
    }

    public function updateShopProduct(Request $request, ShopProduct $shopProduct)
    {
        $validated = $request->validate([
            'product_name'    => 'required|string|max:100',
            'unit'            => 'required|string|max:20',
            'current_price'   => 'required|numeric|min:0',
            'cost'            => 'nullable|numeric|min:0',
            'forecourt_stock' => 'nullable|numeric|min:0',
            'store_stock'     => 'nullable|numeric|min:0',
            'is_active'       => 'boolean',
        ]);

        $shopProduct->update($validated);

        return back()->with('success', 'Shop product updated.');
    }

    // Prices
    public function storePrice(Request $request)
    {
        $station = $request->user()->station;

        $validated = $request->validate([
            'product_id'     => ['required', Rule::exists('products', 'id')->where('station_id', $station->id)],
            'price_per_litre'=> 'required|numeric|min:0.001',
            'effective_from' => 'required|date',
        ]);

        // Close the previous active price (scoped to station via product)
        PriceHistory::where('product_id', $validated['product_id'])
            ->whereHas('product', fn($q) => $q->where('station_id', $station->id))
            ->whereNull('effective_to')
            ->update(['effective_to' => now()->toDateString()]);

        PriceHistory::create(array_merge($validated, [
            'created_by' => auth()->id(),
        ]));

        return back()->with('success', 'Price updated.');
    }
}
