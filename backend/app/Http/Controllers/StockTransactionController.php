<?php

namespace App\Http\Controllers;

use App\Models\ShopProduct;
use App\Models\StockTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockTransactionController extends Controller
{
    public function store(Request $request, ShopProduct $shopProduct)
    {
        $type = $request->input('type');

        $validated = $request->validate([
            'type'         => 'required|in:grn,adj',
            'trans_date'   => 'required|date',
            'quantity'     => ['required', 'numeric', $type === 'adj' ? 'not_in:0' : 'min:0.001'],
            'target'       => 'nullable|in:forecourt,store',
            'document_ref' => 'nullable|string|max:50',
            'notes'        => 'nullable|string|max:255',
        ]);

        $qty    = (float) $validated['quantity'];
        $target = $validated['target'] ?? 'forecourt';

        DB::transaction(function () use ($validated, $shopProduct, $qty, $target) {
            StockTransaction::create([
                'shop_product_id' => $shopProduct->id,
                'station_id'      => $shopProduct->station_id,
                'type'            => $validated['type'],
                'trans_date'      => $validated['trans_date'],
                'quantity'        => $qty,
                'document_ref'    => $validated['document_ref'] ?? null,
                'notes'           => $validated['notes'] ?? null,
                'entered_by'      => auth()->id(),
            ]);

            if ($target === 'store') {
                $shopProduct->increment('store_stock', $qty);
            } elseif ($qty < 0) {
                // Negative adj: drain forecourt first, then store
                $forecourt = (float) $shopProduct->forecourt_stock;
                if (abs($qty) <= $forecourt) {
                    $shopProduct->decrement('forecourt_stock', abs($qty));
                } else {
                    $fromStore = abs($qty) - $forecourt;
                    $shopProduct->forecourt_stock = 0;
                    $shopProduct->store_stock     = max(0, (float) $shopProduct->store_stock - $fromStore);
                    $shopProduct->save();
                }
            } else {
                $shopProduct->increment('forecourt_stock', $qty);
            }
        });

        return back()->with('success', 'Stock transaction recorded.');
    }

    public function destroy(StockTransaction $stockTransaction)
    {
        if ($stockTransaction->type === 'iss') {
            return back()->withErrors(['error' => 'ISS transactions cannot be deleted here.']);
        }

        DB::transaction(function () use ($stockTransaction) {
            $product  = ShopProduct::find($stockTransaction->shop_product_id);
            $qty      = (float) $stockTransaction->quantity;
            // Reverse: add back if qty was positive, reduce if was negative
            $product->increment('forecourt_stock', -$qty);
            $stockTransaction->delete();
        });

        return back()->with('success', 'Transaction deleted.');
    }
}
