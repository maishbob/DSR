<?php

namespace App\Http\Controllers;

use App\Models\OilSale;
use App\Models\ShopProduct;
use App\Models\Shift;
use App\Models\StockTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OilSaleController extends Controller
{
    public function store(Request $request, Shift $shift)
    {
        abort_if($shift->isLocked(), 403, 'Shift is locked.');

        $validated = $request->validate([
            'shop_product_id' => 'required|exists:shop_products,id',
            'opening_stock'   => 'required|numeric|min:0',
            'quantity'        => 'required|numeric|min:0.001',
            'unit_price'      => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated, $shift) {
            $sale = OilSale::create([
                'shift_id'        => $shift->id,
                'shop_product_id' => $validated['shop_product_id'],
                'opening_stock'   => $validated['opening_stock'],
                'quantity'        => $validated['quantity'],
                'unit_price'      => $validated['unit_price'],
                'total_value'     => round($validated['quantity'] * $validated['unit_price'], 2),
                'entered_by'      => auth()->id(),
            ]);

            // Record ISS transaction and decrement forecourt stock
            $product = ShopProduct::find($validated['shop_product_id']);
            StockTransaction::create([
                'shop_product_id' => $product->id,
                'station_id'      => $shift->station_id,
                'type'            => 'iss',
                'trans_date'      => $shift->shift_date,
                'quantity'        => $validated['quantity'],
                'document_ref'    => $shift->dailySalesRecord?->serial_number ?? "SHIFT-{$shift->id}",
                'notes'           => 'Oil sale',
                'entered_by'      => auth()->id(),
            ]);

            // Decrement from forecourt stock first, then store stock
            $qty = (float) $validated['quantity'];
            $forecourt = (float) $product->forecourt_stock;
            if ($qty <= $forecourt) {
                $product->decrement('forecourt_stock', $qty);
            } else {
                $fromStore = $qty - $forecourt;
                $product->forecourt_stock = 0;
                $product->store_stock = max(0, (float) $product->store_stock - $fromStore);
                $product->save();
            }
        });

        return back()->with('success', 'Oil sale recorded.');
    }

    public function destroy(OilSale $oilSale)
    {
        abort_if($oilSale->shift->isLocked(), 403, 'Shift is locked.');

        DB::transaction(function () use ($oilSale) {
            // Reverse the stock ISS — add back to forecourt stock
            $product = ShopProduct::find($oilSale->shop_product_id);
            $product->increment('forecourt_stock', (float) $oilSale->quantity);

            // Remove the corresponding ISS transaction for this shift
            StockTransaction::where('shop_product_id', $oilSale->shop_product_id)
                ->where('station_id', $oilSale->shift->station_id)
                ->where('type', 'iss')
                ->where('trans_date', $oilSale->shift->shift_date)
                ->where('quantity', $oilSale->quantity)
                ->latest()
                ->limit(1)
                ->delete();

            $oilSale->delete();
        });

        return back()->with('success', 'Oil sale removed.');
    }
}
