<?php

namespace App\Http\Controllers;

use App\Models\ShopProduct;
use App\Models\StockTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockTransactionController extends Controller
{
    /**
     * Record a GRN (stock received) or manual adjustment.
     */
    public function store(Request $request, ShopProduct $shopProduct)
    {
        $validated = $request->validate([
            'type'         => 'required|in:grn,adj',
            'trans_date'   => 'required|date',
            'quantity'     => 'required|numeric|min:0.001',
            'document_ref' => 'nullable|string|max:50',
            'notes'        => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($validated, $shopProduct) {
            StockTransaction::create([
                'shop_product_id' => $shopProduct->id,
                'station_id'      => $shopProduct->station_id,
                'type'            => $validated['type'],
                'trans_date'      => $validated['trans_date'],
                'quantity'        => $validated['quantity'],
                'document_ref'    => $validated['document_ref'] ?? null,
                'notes'           => $validated['notes'] ?? null,
                'entered_by'      => auth()->id(),
            ]);

            // GRN goes to forecourt stock; adj can go either way (positive = add to forecourt)
            $shopProduct->increment('forecourt_stock', (float) $validated['quantity']);
        });

        return back()->with('success', 'Stock transaction recorded.');
    }

    public function destroy(StockTransaction $stockTransaction)
    {
        // Only allow deleting GRN/adj (not auto-generated ISS)
        if ($stockTransaction->type === 'iss') {
            return back()->withErrors(['error' => 'ISS transactions cannot be deleted here.']);
        }

        DB::transaction(function () use ($stockTransaction) {
            $product = ShopProduct::find($stockTransaction->shop_product_id);
            $product->decrement('forecourt_stock', (float) $stockTransaction->quantity);
            $stockTransaction->delete();
        });

        return back()->with('success', 'Transaction deleted.');
    }
}
