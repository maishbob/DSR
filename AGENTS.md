# DSR System — Project Brief

## What Is This?

A **Daily Sales Reconciliation (DSR)** system for fuel retail stations. It replaces a legacy desktop application (built in VB/Access) with a modern web-based system. It tracks everything that happens at a fuel station on a daily basis: fuel sold via pumps, credit sales, payments received, deliveries, expenses, tank dips, and generates a reconciled daily report (the DSR).

## Who Uses It

- **Station owners** — manage their stations, view reports
- **Station managers/attendants** — record shifts, meter readings, expenses
- **Accountants** — manage credit customers, payments, reconciliations

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12 (PHP) |
| Frontend | Vue 3 + Inertia.js (SPA, no API layer) |
| Build | Vite |
| Styling | Tailwind CSS + Flowbite |
| Database | MySQL (via Laragon locally) |
| Auth | Laravel Breeze |

## Multi-Tenancy Model

```
owners
  └── stations        (one owner can have many stations)
        └── users     (station users belong to a station)
```

Every data record is scoped to a `station_id`. Users only see their own station's data.

## Core Modules & Pages

### Operations
| Page | Route | Description |
|---|---|---|
| Dashboard | `/dashboard` | KPIs: daily sales, debtors balance, stock levels |
| Shifts | `/shifts` | List of shifts; open a new shift |
| Shift Detail | `/shifts/{id}` | Enter meter readings, expenses, oil sales, card payments, POS, dips |
| Deliveries | `/deliveries` | Record fuel deliveries per tank |

### Accounts
| Page | Route | Description |
|---|---|---|
| Credit Customers | `/credits` | Debtors masterfile — name, contact, credit limit, balance |
| Credit Statement | `/credits/{id}` | Unified ledger: sales + payments, running balance, date filter |
| Payments | `/payments` | Global payments list across all customers |
| Card Recons | `/card-recons` | Credit card batch reconciliations (card name, batch ref, lines) |
| POS Account | `/pos-account` | POS transaction global list |

### Reports
| Page | Route | Description |
|---|---|---|
| Wet Stock | `/reports/wet-stock` | Tank stock levels over time |
| Sales Summary | `/reports/sales-summary` | Sales by product |
| Variance | `/reports/variance` | Meter variance tracking |
| Delivery History | `/reports/delivery-history` | Deliveries over period |
| Credit Statement | `/reports/credit-statement` | Per-customer statement report |

### Settings
| Page | Route | Description |
|---|---|---|
| Station Settings | `/station/settings` | Pumps/nozzles, tanks, products, fuel prices, shop products |

---

## Key Domain Concepts

### Shift
A shift represents one working period at the station (e.g. morning, afternoon). It has:
- `opened_at`, `closed_at`
- Status: `open` → closed when DSR is finalised (no manual "Close Shift" step)

### Meter Reading
Each nozzle has a mechanical meter and an electrical meter (and SHS for solar). A shift reading records opening + closing values. Litres sold = closing − opening.

- Nozzles store `last_mech`, `last_elec`, `last_shs` — these become the opening readings for the next shift automatically.
- When a reading is **saved**, the nozzle's last readings are updated immediately.
- When a reading is **cleared**, the nozzle's last readings revert to the reading's opening values.

### DSR (Daily Sales Reconciliation)
The final daily report. Finalising a DSR:
1. Stamps the shift as closed
2. Locks meter readings
3. Produces the reconciliation document

### Credit Customer
A debtor who buys fuel on credit. Has:
- `initial_opening_balance` — brought-forward balance from legacy system
- Running balance = opening balance + total purchases − total payments
- `discount_multiplier` — price discount
- `is_withholding_vat_agent` — VAT withholding flag

### Payments
Payments against credit customer accounts. Fields:
- `trans_type`: receipts / fuel / lpg / pos / invoice
- `payment_method`: cash / mpesa / bank_transfer / cheque / rtgs / equity_card
- `receipt_no`, `reference`

### Tank
Each tank stores one product. A **Complex Tank** has two dip compartments (Dip 1 + Dip 2). The `is_complex` flag on the tank controls this.

---

## Database Tables (key ones)

```
stations
owners
users
products
tanks                   — is_complex, last_closing_stock, last_dip_stock, dip_2
pump_nozzles            — last_mech, last_elec, last_shs
shifts
meter_readings          — opening_*/closing_* per nozzle per shift
tank_dips               — dip readings per shift
deliveries
credit_customers        — initial_opening_balance, discount_multiplier, pin, vat_number...
credit_sales
payments                — station_id, receipt_no, trans_type
card_recons             — card_name, batch_ref, recon_date
card_recon_lines        — trans_date, ref, amount
pos_transactions        — station_id, trans_date
expenses
oil_sales
card_payments
shop_products
stock_transactions
```

---

## UI Conventions

- **No comma separators** on meter readings — use `.toFixed(n)` not `toLocaleString()`
- **Toast notifications** (bottom-right, 4s auto-dismiss) — no inline flash banners
- **Sidebar** — pin/unpin persisted to localStorage; nav grouped: Operations / Accounts / Reports / Settings
- Flowbite components used for modals, badges, dropdowns
- All currency in **KES**

---

## Important Rules / Decisions

1. **Close Shift is removed** — the shift is closed automatically when the DSR is finalised
2. **Opening readings are real-time** — nozzle `last_*` fields update as soon as a closing reading is saved (not on shift close)
3. **Clearing a reading** reverts the nozzle to its opening values (not zeros)
4. **Linked Tank removed** from tank settings — Complex Tank checkbox replaces it
5. **Main Pump / Nozzle No / Sort Order** fields hidden from nozzle modal (internal only)
6. **Combined ledger** in Credits/Show — sales and payments merged, sorted by date, running balance, brought-forward for date ranges
7. **Trans Type** (Fuel/Receipts/LPG/POS/Invoice) is separate from **Payment Method** (Cash/MPESA/Bank Transfer/Cheque/RTGS/Equity Card)

---

## Local Development

```bash
# Start Laragon (Apache + MySQL)
# App URL: http://dsr.test  (or configured vhost)

# Frontend dev server
cd backend
npm run dev

# Build for production
npm run build

# Migrations
php artisan migrate

# Tinker
php artisan tinker
```

Apache vhost config lives in: `C:\laragon\etc\apache2\sites-enabled\`
