<?php

namespace App\Http\Controllers;

use App\Models\CreditCustomer;
use App\Models\MeterReading;
use App\Models\OilSale;
use App\Models\Payment;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\PumpNozzle;
use App\Models\Shift;
use App\Models\ShopProduct;
use App\Models\Tank;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ImportController extends Controller
{
    public function show(Request $request)
    {
        $station = $request->user()->station;

        return Inertia::render('Station/Import', [
            'station' => $station,
            'counts'  => [
                'products'   => Product::where('station_id', $station->id)->count(),
                'tanks'      => Tank::where('station_id', $station->id)->count(),
                'nozzles'    => PumpNozzle::where('station_id', $station->id)->count(),
                'shifts'     => Shift::where('station_id', $station->id)->count(),
                'readings'   => DB::table('meter_readings')
                    ->join('pump_nozzles', 'meter_readings.nozzle_id', '=', 'pump_nozzles.id')
                    ->where('pump_nozzles.station_id', $station->id)->count(),
                'customers'  => CreditCustomer::where('station_id', $station->id)->count(),
                'sales'      => DB::table('credit_sales')
                    ->join('credit_customers', 'credit_sales.credit_customer_id', '=', 'credit_customers.id')
                    ->where('credit_customers.station_id', $station->id)->count(),
                'payments'   => Payment::where('station_id', $station->id)->count(),
                'shop_products' => ShopProduct::where('station_id', $station->id)->count(),
                'oil_sales'  => OilSale::whereHas('shift', fn($q) => $q->where('station_id', $station->id))->count(),
            ],
        ]);
    }

    // ── Step 1: Products ─────────────────────────────────────────
    public function importProducts(Request $request)
    {
        $request->validate(['csv' => 'required|file|mimes:csv,txt|max:51200']);
        $station = $request->user()->station;
        $rows = $this->readCsv($request->file('csv')->getRealPath());

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $name = trim($row['Product Name'] ?? '');
            if ($name === '') continue;

            $exists = Product::where('station_id', $station->id)
                ->whereRaw('LOWER(product_name) = ?', [strtolower($name)])
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $product = Product::create([
                'station_id'    => $station->id,
                'product_name'  => $name,
                'cost_per_litre' => $this->cleanAmount($row['Cost Per Ltr'] ?? '0'),
                'is_active'     => true,
            ]);

            // Create initial price history
            $price = $this->cleanAmount($row['Price Per Ltr'] ?? '0');
            if ($price > 0) {
                PriceHistory::create([
                    'product_id'      => $product->id,
                    'price_per_litre' => $price,
                    'effective_from'  => '2020-01-01',
                ]);
            }

            $created++;
        }

        return back()->with('success', "Products: {$created} created, {$skipped} skipped.")
            ->with('importStats', ['created' => $created, 'skipped' => $skipped]);
    }

    // ── Step 2: Tanks ────────────────────────────────────────────
    public function importTanks(Request $request)
    {
        $request->validate(['csv' => 'required|file|mimes:csv,txt|max:51200']);
        $station = $request->user()->station;
        $rows = $this->readCsv($request->file('csv')->getRealPath());

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $name = trim($row['Combinedname'] ?? '');
            if ($name === '') continue;

            $exists = Tank::where('station_id', $station->id)
                ->whereRaw('LOWER(tank_name) = ?', [strtolower($name)])
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $productName = trim($row['Productname'] ?? '');
            $product = Product::where('station_id', $station->id)
                ->whereRaw('LOWER(product_name) = ?', [strtolower($productName)])
                ->first();

            if (! $product) {
                $skipped++;
                continue;
            }

            Tank::create([
                'station_id'         => $station->id,
                'product_id'         => $product->id,
                'tank_name'          => $name,
                'tank_capacity'      => 0, // Not in legacy CSV
                'is_complex'         => (int) trim($row['Complextank'] ?? '0') === 1,
                'last_closing_stock' => $this->cleanAmount($row['Lastclosingstock'] ?? '0'),
                'last_dip_stock'     => $this->cleanAmount($row['Lastdipstock'] ?? '0'),
                'last_dip_2'         => $this->cleanAmount($row['Lastclosingstock2'] ?? '0'),
            ]);

            $created++;
        }

        return back()->with('success', "Tanks: {$created} created, {$skipped} skipped.")
            ->with('importStats', ['created' => $created, 'skipped' => $skipped]);
    }

    // ── Step 3: Pumps / Nozzles ──────────────────────────────────
    public function importPumps(Request $request)
    {
        $request->validate(['csv' => 'required|file|mimes:csv,txt|max:51200']);
        $station = $request->user()->station;
        $rows = $this->readCsv($request->file('csv')->getRealPath());

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $combinedName = trim($row['Combined Name'] ?? '');
            if ($combinedName === '') continue;

            $exists = PumpNozzle::where('station_id', $station->id)
                ->whereRaw('LOWER(nozzle_name) = ?', [strtolower($combinedName)])
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            // Resolve product from pump name (e.g. "UX1 UNLEADED" → UNLEADED)
            $pumpName = trim($row['Pump Name'] ?? '');
            $product = Product::where('station_id', $station->id)
                ->whereRaw('LOWER(product_name) = ?', [strtolower($pumpName)])
                ->first();

            if (! $product) {
                $skipped++;
                continue;
            }

            // Resolve tank
            $defaultTankName = trim($row['Default Tank Name'] ?? '');
            $tank = Tank::where('station_id', $station->id)
                ->whereRaw('LOWER(tank_name) = ?', [strtolower($defaultTankName)])
                ->first();

            PumpNozzle::create([
                'station_id' => $station->id,
                'product_id' => $product->id,
                'tank_id'    => $tank?->id,
                'nozzle_name' => $combinedName,
                'nozzle_ref'  => trim($row['Pump Number'] ?? ''),
                'main_pump'   => ((int) trim($row['Main Pump'] ?? '0')) ?: null,
                'nozzle_no'   => ((int) trim($row['Nozzle No'] ?? '0')) ?: null,
                'last_mech'   => $this->cleanAmount($row['Last Reading Mech'] ?? '0'),
                'last_elec'   => $this->cleanAmount($row['Last Reading Elec'] ?? '0'),
                'last_shs'    => $this->cleanAmount($row['Last Reading Shs'] ?? '0'),
            ]);

            $created++;
        }

        return back()->with('success', "Nozzles: {$created} created, {$skipped} skipped.")
            ->with('importStats', ['created' => $created, 'skipped' => $skipped]);
    }

    // ── Step 4: Daily Shifts ─────────────────────────────────────
    public function importShifts(Request $request)
    {
        $request->validate(['csv' => 'required|file|mimes:csv,txt|max:51200']);
        $station = $request->user()->station;
        $rows = $this->readCsv($request->file('csv')->getRealPath());

        // Group rows by parsed date, preserving CSV order within each date
        $byDate = [];
        foreach ($rows as $row) {
            $dateStr = trim($row['Date'] ?? '');
            $date = $this->parseLegacyDate($dateStr);
            if (! $date) continue;

            $key = $date->format('Y-m-d');
            $byDate[$key][] = [
                'date'       => $date,
                'dsr_number' => preg_replace('/[^0-9]/', '', trim($row['Daily Record Id'] ?? '')),
            ];
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::beginTransaction();
        try {
            foreach ($byDate as $dateKey => $dateRows) {
                // Deduplicate by DSR number, keep first two per date
                $seen = [];
                $unique = [];
                foreach ($dateRows as $r) {
                    if (isset($seen[$r['dsr_number']])) {
                        $skipped++;
                        continue;
                    }
                    $seen[$r['dsr_number']] = true;
                    $unique[] = $r;
                    if (count($unique) >= 2) break;
                }

                foreach ($unique as $idx => $entry) {
                    $shiftType = $idx === 0 ? 'day' : 'night';

                    $existing = Shift::where('station_id', $station->id)
                        ->where('shift_date', $dateKey)
                        ->where('shift_type', $shiftType)
                        ->first();

                    if ($existing) {
                        // Backfill dsr_number on phantom shifts (created by credit import without one)
                        if ($existing->dsr_number === null && $entry['dsr_number'] !== '') {
                            $existing->update(['dsr_number' => $entry['dsr_number']]);
                            $updated++;
                        } else {
                            $skipped++;
                        }
                        continue;
                    }

                    Shift::create([
                        'station_id' => $station->id,
                        'shift_date' => $dateKey,
                        'shift_type' => $shiftType,
                        'dsr_number' => $entry['dsr_number'] !== '' ? $entry['dsr_number'] : null,
                        'opened_at'  => $shiftType === 'day'
                            ? $entry['date']->copy()->setTime(6, 0)
                            : $entry['date']->copy()->setTime(18, 0),
                        'closed_at'  => $shiftType === 'day'
                            ? $entry['date']->copy()->setTime(18, 0)
                            : $entry['date']->copy()->addDay()->setTime(6, 0),
                        'status'     => 'locked',
                    ]);

                    $created++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Shift import failed: ' . $e->getMessage());
        }

        return back()->with('success', "Shifts: {$created} created, {$updated} dsr_numbers backfilled, {$skipped} skipped.")
            ->with('importStats', ['created' => $created, 'updated' => $updated, 'skipped' => $skipped]);
    }

    // ── Step 5: Daily Pump Readings ──────────────────────────────
    public function importReadings(Request $request)
    {
        $request->validate(['csv' => 'required|file|mimes:csv,txt|max:51200']);
        $station = $request->user()->station;
        $rows = $this->readCsv($request->file('csv')->getRealPath());

        // Pre-load nozzle lookup: lowercase name => id
        $nozzleMap = PumpNozzle::where('station_id', $station->id)
            ->pluck('id', 'nozzle_name')
            ->mapWithKeys(fn($id, $name) => [strtolower($name) => $id])
            ->toArray();

        // Pre-load shift lookup: "Y-m-d|type" => id
        $shiftCache = [];
        Shift::where('station_id', $station->id)
            ->get(['id', 'shift_date', 'shift_type'])
            ->each(function (Shift $s) use (&$shiftCache) {
                $shiftCache[$s->shift_date->format('Y-m-d') . '|' . $s->shift_type] = $s->id;
            });

        // Track which shift+nozzle combos we've already inserted (to handle duplicates in CSV)
        $inserted = [];

        $created = 0;
        $skipped = 0;
        $batch = [];
        $now = now();

        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                $pumpName = trim($row['Pumpname'] ?? '');
                $dateStr  = trim($row['Date'] ?? '');
                $dailyRecordId = str_replace(',', '', trim($row['Dailyrecordid'] ?? ''));

                if ($pumpName === '' || $dateStr === '' || $dailyRecordId === '' || $dailyRecordId === '0') {
                    $skipped++;
                    continue;
                }

                $nozzleId = $nozzleMap[strtolower($pumpName)] ?? null;
                if (! $nozzleId) {
                    $skipped++;
                    continue;
                }

                $date = $this->parseLegacyDate($dateStr);
                if (! $date) {
                    $skipped++;
                    continue;
                }

                // Look up shift by dsr_number
                if (! isset($shiftCache[$dailyRecordId])) {
                    $shift = Shift::where('station_id', $station->id)
                        ->where('dsr_number', $dailyRecordId)
                        ->first();

                    if (! $shift) {
                        $skipped++;
                        continue;
                    }
                    $shiftCache[$dailyRecordId] = $shift->id;
                }

                $shiftId = $shiftCache[$dailyRecordId];

                // Skip duplicate shift+nozzle combo
                $dupeKey = $shiftId . '|' . $nozzleId;
                if (isset($inserted[$dupeKey])) {
                    $skipped++;
                    continue;
                }
                $inserted[$dupeKey] = true;

                $openElec  = $this->cleanAmount($row['Openmeterelectrical'] ?? '0');
                $closeElec = $this->cleanAmount($row['Closemeterelectrical'] ?? '0');
                $openMech  = $this->cleanAmount($row['Openmetermechanical'] ?? '0');
                $closeMech = $this->cleanAmount($row['Closemetermechanical'] ?? '0');
                $openShs   = $this->cleanAmount($row['Openmetershs'] ?? '0');
                $closeShs  = $this->cleanAmount($row['Closemetershs'] ?? '0');

                $litresSold = $closeElec >= $openElec ? round($closeElec - $openElec, 3) : 0;
                $shsSold    = $closeShs >= $openShs ? round($closeShs - $openShs, 2) : 0;

                $batch[] = [
                    'shift_id'            => $shiftId,
                    'nozzle_id'           => $nozzleId,
                    'opening_mechanical'  => $openMech,
                    'closing_mechanical'  => $closeMech,
                    'opening_electrical'  => $openElec,
                    'closing_electrical'  => $closeElec,
                    'opening_shs'         => $openShs,
                    'closing_shs'         => $closeShs,
                    'litres_sold'         => $litresSold,
                    'shs_sold'            => $shsSold,
                    'is_locked'           => true,
                    'created_at'          => $date->copy()->startOfDay(),
                    'updated_at'          => $now,
                ];

                $created++;

                if (count($batch) >= 500) {
                    DB::table('meter_readings')->insert($batch);
                    $batch = [];
                }
            }

            if (count($batch) > 0) {
                DB::table('meter_readings')->insert($batch);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Readings import failed: ' . $e->getMessage());
        }

        return back()->with('success', "Meter readings: {$created} created, {$skipped} skipped.")
            ->with('importStats', ['created' => $created, 'skipped' => $skipped]);
    }

    // ── Step 6: Clients + Transactions ───────────────────────────
    public function importCredits(Request $request)
    {
        $request->validate([
            'clients_csv'      => 'required|file|mimes:csv,txt|max:51200',
            'transactions_csv' => 'required|file|mimes:csv,txt|max:51200',
        ]);

        $station = $request->user()->station;

        $defaultProductId = Product::where('station_id', $station->id)
            ->where('is_active', true)
            ->value('id');

        if (! $defaultProductId) {
            return back()->with('error', 'No active product found. Import products first.');
        }

        $clientsPath = $request->file('clients_csv')->getRealPath();
        $transactionsPath = $request->file('transactions_csv')->getRealPath();

        $customerMap = [];
        $shiftCache = [];
        $stats = [
            'customers_created' => 0,
            'customers_skipped' => 0,
            'sales_created'     => 0,
            'payments_created'  => 0,
            'shifts_created'    => 0,
            'rows_skipped'      => 0,
        ];

        // Import Clients
        foreach ($this->readCsv($clientsPath) as $row) {
            $name = trim($row['Name'] ?? '');
            if ($name === '') {
                $stats['rows_skipped']++;
                continue;
            }

            $existing = CreditCustomer::where('station_id', $station->id)
                ->whereRaw('LOWER(customer_name) = ?', [strtolower($name)])
                ->first();

            if ($existing) {
                $customerMap[strtolower($name)] = $existing->id;
                $stats['customers_skipped']++;
                continue;
            }

            $customer = CreditCustomer::create([
                'station_id'               => $station->id,
                'customer_name'            => $name,
                'contact'                  => trim($row['Contact'] ?? ''),
                'phone'                    => trim($row['Telephone'] ?? ''),
                'email'                    => trim($row['Email Address'] ?? ''),
                'address'                  => trim($row['Address'] ?? ''),
                'city'                     => trim($row['City'] ?? ''),
                'pin'                      => trim($row['Pin'] ?? ''),
                'vat_number'               => trim($row['Vat'] ?? ''),
                'is_withholding_vat_agent' => (int) trim($row['Withholdingvat Agent'] ?? '0') === 1,
                'credit_limit'             => abs($this->cleanAmount($row['Creditlimit'] ?? '0')),
                'discount_multiplier'      => $this->cleanAmount($row['Discountmultiplier'] ?? '0'),
                'initial_opening_balance'  => $this->cleanAmount($row['Broughtforward'] ?? $row['Openingbalance'] ?? '0'),
                'is_active'                => true,
            ]);

            $customerMap[strtolower($name)] = $customer->id;
            $stats['customers_created']++;
        }

        // Import Transactions
        Shift::where('station_id', $station->id)
            ->where('status', 'locked')
            ->get()
            ->each(function (Shift $s) use (&$shiftCache) {
                $key = $s->shift_date->format('Y-m-d') . '|' . $s->shift_type;
                $shiftCache[$key] = $s->id;
            });

        DB::beginTransaction();
        try {
            foreach ($this->readCsv($transactionsPath) as $row) {
                $clientName = trim($row['Client Name'] ?? '');
                $transType  = strtolower(trim($row['Trans Type'] ?? ''));
                $dateStr    = trim($row['Dsr Date'] ?? '');
                $amount     = $this->cleanAmount($row['Amount'] ?? '0');
                $receiptNo  = trim($row['Receipt No'] ?? '');
                $chqNo      = trim($row['Chq No'] ?? '');
                $invoiceNo  = trim($row['Invoice No'] ?? '');
                $litres     = $this->cleanAmount($row['Litres'] ?? '0');
                $vatAmount  = $this->cleanAmount($row['Vat Amount'] ?? '0');
                $whtAmount  = $this->cleanAmount($row['Withholding Vat Amount'] ?? '0');

                if ($clientName === '' || $dateStr === '' || $amount == 0) {
                    $stats['rows_skipped']++;
                    continue;
                }

                $customerId = $this->resolveCustomer($clientName, $station->id, $customerMap, $stats);
                if (! $customerId) {
                    $stats['rows_skipped']++;
                    continue;
                }

                $date = $this->parseLegacyDate($dateStr);
                if (! $date) {
                    $stats['rows_skipped']++;
                    continue;
                }

                if ($transType === 'receipts' || $transType === 'receipt') {
                    $paymentMethod = 'cash';
                    if (str_starts_with(strtoupper($chqNo), 'MP-')) {
                        $paymentMethod = 'mpesa';
                    } elseif ($chqNo !== '' && $chqNo !== '0') {
                        $paymentMethod = 'cheque';
                    }

                    Payment::create([
                        'credit_customer_id' => $customerId,
                        'station_id'         => $station->id,
                        'payment_date'       => $date->format('Y-m-d'),
                        'receipt_no'         => $receiptNo ?: null,
                        'trans_type'         => 'receipts',
                        'amount'             => abs($amount),
                        'payment_method'     => $paymentMethod,
                        'reference'          => $chqNo ?: null,
                        'notes'              => 'Legacy import',
                        'is_locked'          => true,
                    ]);
                    $stats['payments_created']++;
                } else {
                    $saleType = match ($transType) {
                        'oil'  => 'oil',
                        'lpg'  => 'other',
                        default => 'fuel',
                    };

                    $shiftId = $this->getOrCreateLegacyShift($date, $station->id, $shiftCache, $stats);
                    $absAmount = abs($amount);
                    $qty = abs($litres);
                    $price = ($qty > 0) ? round($absAmount / $qty, 4) : 0;

                    DB::table('credit_sales')->insert([
                        'credit_customer_id' => $customerId,
                        'product_id'         => $defaultProductId,
                        'shift_id'           => $shiftId,
                        'debit_note'         => $invoiceNo ?: null,
                        'type'               => $saleType,
                        'quantity'           => $qty,
                        'price_applied'      => $price,
                        'total_value'        => $absAmount,
                        'vat_amount'         => $vatAmount != 0 ? abs($vatAmount) : round($absAmount - $absAmount / 1.16, 2),
                        'wht_amount'         => $whtAmount != 0 ? abs($whtAmount) : round($absAmount * 0.0172, 2),
                        'notes'              => 'Legacy import',
                        'is_locked'          => true,
                        'created_at'         => $date->copy()->startOfDay(),
                        'updated_at'         => now(),
                    ]);
                    $stats['sales_created']++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Credit import failed: ' . $e->getMessage());
        }

        return back()->with('success', 'Credit data imported.')
            ->with('importStats', $stats);
    }

    // ── Step 7: Oil Stock (Shop Products) ────────────────────────
    public function importOilStock(Request $request)
    {
        $request->validate(['csv' => 'required|file|mimes:csv,txt|max:51200']);
        $station = $request->user()->station;
        $rows = $this->readCsv($request->file('csv')->getRealPath());

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $name = trim($row['Itemname'] ?? '');
            if ($name === '') {
                $skipped++;
                continue;
            }

            $exists = ShopProduct::where('station_id', $station->id)
                ->whereRaw('LOWER(product_name) = ?', [strtolower($name)])
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            ShopProduct::create([
                'station_id'      => $station->id,
                'product_name'    => $name,
                'unit'            => 'piece',
                'current_price'   => $this->cleanAmount($row['Price'] ?? '0'),
                'cost'            => $this->cleanAmount($row['Cost'] ?? '0'),
                'forecourt_stock' => (float) trim($row['Currentstock'] ?? '0'),
                'store_stock'     => (float) trim($row['Storestock'] ?? '0'),
                'is_active'       => true,
            ]);
            $created++;
        }

        return back()->with('success', "Oil stock: {$created} created, {$skipped} skipped.")
            ->with('importStats', ['created' => $created, 'skipped' => $skipped]);
    }

    // ── Step 8: Oil Daily Sales ──────────────────────────────────
    public function importOilSales(Request $request)
    {
        $request->validate(['csv' => 'required|file|mimes:csv,txt|max:51200']);
        $station = $request->user()->station;
        $rows = $this->readCsv($request->file('csv')->getRealPath());

        // Build lookup: item name (lowercase) → shop_product_id
        $productMap = ShopProduct::where('station_id', $station->id)
            ->pluck('id', 'product_name')
            ->mapWithKeys(fn($id, $name) => [strtolower($name) => $id])
            ->toArray();

        // Build lookup: dsr_number → shift_id (digits-only keys to survive CSV comma-formatting)
        $shiftMap = Shift::where('station_id', $station->id)
            ->whereNotNull('dsr_number')
            ->get(['id', 'dsr_number'])
            ->mapWithKeys(fn($s) => [preg_replace('/[^0-9]/', '', $s->dsr_number) => $s->id])
            ->toArray();

        $created = 0;
        $skipped = 0;
        $noShift = 0;
        $batch = [];

        DB::beginTransaction();
        try {
            // Delete existing oil sales for this station so reimport is idempotent
            $shiftIds = Shift::where('station_id', $station->id)->pluck('id');
            DB::table('oil_sales')->whereIn('shift_id', $shiftIds)->delete();

            foreach ($rows as $row) {
                $itemName      = trim($row['Itemname'] ?? '');
                // Strip commas/spaces — Clarion formats numbers as "3,435"
                $dailyRecordId = preg_replace('/[^0-9]/', '', trim($row['Dailyrecordid'] ?? ''));
                $salesQty      = (float) trim($row['Salesqty'] ?? '0');

                if ($itemName === '' || $dailyRecordId === '') {
                    $skipped++;
                    continue;
                }

                $shopProductId = $productMap[strtolower($itemName)] ?? null;
                $shiftId       = $shiftMap[$dailyRecordId] ?? null;

                if (! $shopProductId) {
                    $skipped++;
                    continue;
                }
                if (! $shiftId) {
                    $noShift++;
                    continue;
                }
                if ($salesQty == 0) {
                    $skipped++;
                    continue;
                }

                $batch[] = [
                    'shift_id'        => $shiftId,
                    'shop_product_id' => $shopProductId,
                    'opening_stock'   => (float) trim($row['Openingstock'] ?? '0'),
                    'quantity'        => $salesQty,
                    'unit_price'      => $this->cleanAmount($row['Price'] ?? '0'),
                    'total_value'     => $this->cleanAmount($row['Salesshs'] ?? '0'),
                    'entered_by'      => null,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];

                if (count($batch) >= 500) {
                    DB::table('oil_sales')->insert($batch);
                    $created += count($batch);
                    $batch = [];
                }
            }

            if (count($batch) > 0) {
                DB::table('oil_sales')->insert($batch);
                $created += count($batch);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Oil sales import failed: ' . $e->getMessage());
        }

        $msg = "Oil sales: {$created} imported, {$skipped} skipped (zero qty / unknown product)";
        if ($noShift > 0) {
            $msg .= ", {$noShift} skipped (DSR number not found — re-upload shifts CSV first if this is high)";
        }

        return back()->with('success', $msg)
            ->with('importStats', ['created' => $created, 'skipped' => $skipped, 'no_shift' => $noShift]);
    }

    // ── Shared helpers ───────────────────────────────────────────

    private function resolveCustomer(string $name, int $stationId, array &$map, array &$stats): ?int
    {
        $key = strtolower(trim($name));

        if (isset($map[$key])) return $map[$key];

        $customer = CreditCustomer::where('station_id', $stationId)
            ->whereRaw('LOWER(customer_name) = ?', [$key])
            ->first();

        if ($customer) {
            $map[$key] = $customer->id;
            return $customer->id;
        }

        $customer = CreditCustomer::create([
            'station_id'              => $stationId,
            'customer_name'           => trim($name),
            'initial_opening_balance' => 0,
            'is_active'               => true,
        ]);
        $map[$key] = $customer->id;
        $stats['customers_created']++;
        return $customer->id;
    }

    private function getOrCreateLegacyShift(Carbon $date, int $stationId, array &$cache, array &$stats): int
    {
        $key = $date->format('Y-m-d') . '|day';

        if (isset($cache[$key])) return $cache[$key];

        $shift = Shift::firstOrCreate(
            ['station_id' => $stationId, 'shift_date' => $date->format('Y-m-d'), 'shift_type' => 'day'],
            ['opened_at' => $date->copy()->startOfDay(), 'closed_at' => $date->copy()->endOfDay(), 'status' => 'locked']
        );

        if ($shift->wasRecentlyCreated) $stats['shifts_created']++;

        $cache[$key] = $shift->id;
        return $shift->id;
    }

    private function parseLegacyDate(string $dateStr): ?Carbon
    {
        $dateStr = trim($dateStr);
        try {
            return Carbon::createFromFormat('n/j/Y', $dateStr)->startOfDay();
        } catch (\Exception $e) {
            foreach (['m/d/Y', 'n/d/Y', 'Y-m-d', 'd/m/Y'] as $fmt) {
                try {
                    return Carbon::createFromFormat($fmt, $dateStr)->startOfDay();
                } catch (\Exception $e) {
                    continue;
                }
            }
            return null;
        }
    }

    private function cleanAmount(string $value): float
    {
        $cleaned = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $value));
        return (float) $cleaned;
    }

    private function readCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        if (! $handle) return [];

        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);
            return [];
        }

        $header = array_map(fn($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)), $header);

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) !== count($header)) continue;
            $rows[] = array_combine($header, $data);
        }

        fclose($handle);
        return $rows;
    }
}
