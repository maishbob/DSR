# Legacy Import — Handoff Document

## What This Is

We are importing data from a legacy Clarion/VB/Access fuel station system into the DSR (Daily Sales Reconciliation) Laravel web app. The legacy system manages fuel stations for **Broadway Petroleum**, and we're migrating station-by-station.

## Current State

### Station 1: Sonning (complete)

| Step          | CSV File               | Status | Notes                                                                                          |
| ------------- | ---------------------- | ------ | ---------------------------------------------------------------------------------------------- |
| Products      | products.csv           | Done   | 4 products: Unleaded, Kerosene, Diesel, V-Power with prices                                    |
| Tanks         | tanks.csv              | Done   | 7 tanks imported (4 original + 3 re-imported: DIESEL TANK 2, UNLEADED TANK 2, UNLEADED TANK 3) |
| Pumps/Nozzles | pumps.csv              | Done   | 17 nozzles imported                                                                            |
| Daily Shifts  | dailyShifts.csv        | Done   | 2,701 shifts (1,709 original + 992 auto-created during readings import)                        |
| Pump Readings | dailyPumpReadings.csv  | Done   | 35,296 readings created from 55,030 CSV rows (19,734 skipped: duplicates, missing nozzle/date) |
| Clients       | clients.csv            | Done   | 96 customers (90 imported + 6 auto-created stubs from transactions)                            |
| Transactions  | clientTransactions.csv | Done   | 50,225 credit sales + 4,637 payments                                                           |

### Station 2: Baba Dogo

- Created (ID 2) under Broadway Petroleum (owner ID 1)
- Cleaned of accidentally imported Sonning data (products, tanks, nozzles removed)
- No data imported yet — waiting for CSV exports from legacy system

## Architecture

### Import Page (web UI)

- **Route**: `GET /station/import` → `ImportController@show`
- **Vue Page**: `resources/js/Pages/Station/Import.vue`
- **Sidebar**: Settings → Import Legacy Data

The page shows 6 numbered import steps, each with its own file upload and POST endpoint:

| Step        | Route Name                | Controller Method | CSV Fields                              |
| ----------- | ------------------------- | ----------------- | --------------------------------------- |
| 1. Products | `station.import.products` | `importProducts`  | Single: `csv`                           |
| 2. Tanks    | `station.import.tanks`    | `importTanks`     | Single: `csv`                           |
| 3. Pumps    | `station.import.pumps`    | `importPumps`     | Single: `csv`                           |
| 4. Shifts   | `station.import.shifts`   | `importShifts`    | Single: `csv`                           |
| 5. Readings | `station.import.readings` | `importReadings`  | Single: `csv`                           |
| 6. Credits  | `station.import.credits`  | `importCredits`   | Two: `clients_csv` + `transactions_csv` |

Each step is idempotent — duplicates are detected and skipped on re-run.

### Key Files

- `app/Http/Controllers/ImportController.php` — All import logic
- `resources/js/Pages/Station/Import.vue` — Import page UI
- `app/Console/Commands/ImportLegacyData.php` — Original artisan command (clients+transactions only, still works)
- `app/Http/Middleware/HandleInertiaRequests.php` — Shares `importStats` flash data
- `routes/web.php` — Import routes under the auth middleware group

### CSV Files Location

All legacy CSVs are in: `backend/database/migrations/` (not ideal, but that's where they are)

## CSV Column Mappings

### products.csv

```
"Product Name" → product_name
"Price Per Ltr" → price_histories.price_per_litre (effective_from = 2020-01-01)
"Cost Per Ltr"  → cost_per_litre
```

### tanks.csv

```
"Combinedname"      → tank_name (e.g. "DIESEL TANK 1")
"Productname"       → matched to products table by name (case-insensitive)
"Complextank"       → is_complex (0/1)
"Lastclosingstock"  → last_closing_stock
"Lastdipstock"      → last_dip_stock
"Lastclosingstock2" → last_dip_2
```

Note: tank_capacity is not in the legacy CSV — set to 0.

### pumps.csv

```
"Combined Name"     → nozzle_name (e.g. "UX1 UNLEADED")
"Pump Name"         → matched to products table (e.g. "UNLEADED" → product_id)
"Default Tank Name" → matched to tanks table (e.g. "UNLEADED TANK 1" → tank_id)
"Pump Number"       → nozzle_ref (e.g. "UX1")
"Main Pump"         → main_pump
"Nozzle No"         → nozzle_no
"Last Reading Mech" → last_mech
"Last Reading Elec" → last_elec
"Last Reading Shs"  → last_shs
```

### dailyShifts.csv

```
"Date"       → shift_date (format: " M/DD/YYYY", space-prefixed)
"Serial No"  → odd = day shift, even = night shift
"Daily Record Id" → used to link pump readings to shifts
```

Fuel sales qty columns (Fuel Sales Qty1-5) are present but NOT currently imported.

### dailyPumpReadings.csv

```
"Dailyrecordid"        → links to dailyShifts "Daily Record Id"; odd=day, even=night
"Pumpname"             → matched to pump_nozzles.nozzle_name (e.g. "UX1 UNLEADED")
"Date"                 → shift date
"Openmeterelectrical"  → opening_electrical
"Closemeterelectrical" → closing_electrical
"Openmetershs"         → opening_shs
"Closemetershs"        → closing_shs
"Openmetermechanical"  → opening_mechanical
"Closemetermechanical" → closing_mechanical
```

Litres sold and SHS sold are calculated: closing - opening.

### clients.csv

```
"Name"                  → customer_name
"Telephone"             → phone
"Contact"               → contact
"Email Address"         → email
"Address"               → address
"City"                  → city
"Pin"                   → pin (KRA PIN)
"Vat"                   → vat_number
"Withholdingvat Agent"  → is_withholding_vat_agent (0/1)
"Creditlimit"           → credit_limit
"Discountmultiplier"    → discount_multiplier
"Broughtforward"        → initial_opening_balance
```

### clientTransactions.csv

```
"Client Name"              → matched to credit_customers by name
"Trans Type"               → "Receipts" = payment, "Fuel"/"Oil" = credit sale
"Dsr Date"                 → payment_date or shift date
"Amount"                   → amount (negative for receipts in legacy, we use abs())
"Receipt No"               → receipt_no
"Chq No"                   → reference; "MP-..." = mpesa, non-empty = cheque, else cash
"Invoice No"               → debit_note on credit sales
"Litres"                   → quantity on credit sales
"Vat Amount"               → vat_amount
"Withholding Vat Amount"   → wht_amount
```

## Data Quirks

1. **Amounts have comma separators** inside quoted CSV fields: `"   1,343,328.060"`. The `cleanAmount()` helper strips commas, spaces, and non-numeric chars.

2. **Dates are space-prefixed**: `" 5/01/2020"`. The `parseLegacyDate()` helper trims and tries `n/j/Y` first (M/D/YYYY).

3. **Credit sales bypass the model's `saving()` hook** — inserted via `DB::table('credit_sales')->insert()` because the boot hook recalculates `total_value = quantity * price`, but legacy data only has the total amount (quantity and price are often 0).

4. **All imported records are marked `is_locked = true`** to prevent editing.

5. **Legacy shifts are created with `status = 'locked'`** and no `opened_by`/`closed_by` user.

6. **Customers in transactions but not in clients.csv** are auto-created as stubs (name only, zero balance).

7. **Payment method detection** from `Chq No` field: starts with `MP-` = mpesa, non-empty = cheque, empty = cash.

8. **The `Dailyrecordid` in pump readings** maps to `Daily Record Id` in shifts. Odd IDs are day shifts, even IDs are night shifts for the same date.

## How to Add More CSV Import Steps

1. **Controller**: Add a new public method to `ImportController.php` following the pattern of existing methods:
   - Validate file upload
   - Read CSV with `$this->readCsv()`
   - Clean amounts with `$this->cleanAmount()`
   - Parse dates with `$this->parseLegacyDate()`
   - Match to existing records by name (case-insensitive)
   - Skip duplicates
   - Return with `importStats` flash data

2. **Route**: Add a POST route in `routes/web.php` under the legacy import section:

   ```php
   Route::post('/station/import/newtype', [ImportController::class, 'importNewType'])->name('station.import.newtype');
   ```

3. **Vue Page**: Add an entry to the `steps` array in `Import.vue`:

   ```js
   {
       key: 'newtype',
       title: 'New Type',
       desc: 'newfile.csv — Description of what this imports.',
       route: 'station.import.newtype',
       fields: [{ name: 'csv', label: 'newfile.csv', accept: '.csv,.txt' }],
       countKey: 'newtype',  // must match a key in the counts prop
       requires: 'products', // optional dependency
   },
   ```

4. **Counts**: Add the count query in `ImportController@show`:
   ```php
   'newtype' => SomeModel::where('station_id', $station->id)->count(),
   ```

## Potential Future CSV Imports

The legacy system likely has more exportable data. Possible candidates:

- Tank dip readings (daily stock measurements)
- Deliveries (fuel delivery records)
- Expenses
- Card payments / POS transactions
- Oil sales / shop product sales

When new CSVs arrive, check headers with:

```bash
head -3 backend/database/migrations/newfile.csv
```

Then follow the steps above to add a new import handler.

## Dev Environment

- **App URL**: http://dsr.test (Laragon Apache vhost)
- **Frontend**: `cd backend && npm run dev` (Vite on port 5173)
- **Database**: MySQL via Laragon, database name `dsr_saas`
- **PHP**: via Laragon
- **Test user**: owner@dsr.test / (check seeder for password)

## Database Schema Reference

Key tables and their relationships for import:

```
stations (id, owner_id, station_name)
products (id, station_id, product_name, cost_per_litre)
price_histories (id, product_id, price_per_litre, effective_from, effective_to)
tanks (id, station_id, product_id, tank_name, tank_capacity, is_complex, last_closing_stock, last_dip_stock, last_dip_2)
pump_nozzles (id, station_id, product_id, tank_id, nozzle_name, nozzle_ref, main_pump, nozzle_no, last_mech, last_elec, last_shs)
shifts (id, station_id, shift_date, shift_type[day/night], opened_at, closed_at, status[open/closed/locked])
meter_readings (id, shift_id, nozzle_id, opening_mechanical, closing_mechanical, opening_electrical, closing_electrical, opening_shs, closing_shs, litres_sold, shs_sold, is_locked)
credit_customers (id, station_id, customer_name, contact, phone, email, address, city, pin, vat_number, is_withholding_vat_agent, credit_limit, discount_multiplier, initial_opening_balance)
credit_sales (id, credit_customer_id, product_id, shift_id, debit_note, type[fuel/oil/other], quantity, price_applied, total_value, vat_amount, wht_amount, is_locked)
payments (id, credit_customer_id, station_id, payment_date, receipt_no, trans_type[receipts/fuel/lpg/pos/invoice], amount, payment_method[cash/mpesa/bank_transfer/cheque/rtgs/equity_card/other], reference, is_locked)
```

Unique constraints:

- `shifts`: unique on (station_id, shift_date, shift_type)
- `pump_nozzles`: unique on (station_id, nozzle_name)
- `meter_readings`: unique on (shift_id, nozzle_id)
