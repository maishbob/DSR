<?php

namespace App\Http\Controllers;

use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\PumpNozzle;
use App\Models\ShopProduct;
use App\Models\Station;
use App\Models\Tank;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StationController extends Controller
{
    public function show(Request $request)
    {
        $station = $request->user()->station->load([
            'products.priceHistories' => fn($q) => $q->orderByDesc('effective_from'),
            'products.tanks',
            'tanks.product',
            'pumpNozzles.product',
            'pumpNozzles.tank',
            'pumpNozzles.latestReading',
            'shopProducts.stockTransactions.enteredBy',
            'shopProducts.oilSales.shift.dailySalesRecord',
        ]);

        return Inertia::render('Station/Settings', [
            'station' => $station,
        ]);
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
            'linked_tank_id'     => 'nullable|exists:tanks,id',
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
        $validated = $request->validate([
            'product_id'     => 'required|exists:products,id',
            'price_per_litre'=> 'required|numeric|min:0.001',
            'effective_from' => 'required|date',
        ]);

        // Close the previous active price
        PriceHistory::where('product_id', $validated['product_id'])
            ->whereNull('effective_to')
            ->update(['effective_to' => now()->toDateString()]);

        PriceHistory::create(array_merge($validated, [
            'created_by' => auth()->id(),
        ]));

        return back()->with('success', 'Price updated.');
    }
}
