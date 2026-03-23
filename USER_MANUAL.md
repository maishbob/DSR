# DSR System — User Manual

**Daily Sales Reconciliation System for Fuel Stations**
Version 1.0 | March 2026

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Roles and Access](#2-roles-and-access)
3. [Logging In](#3-logging-in)
4. [Navigation](#4-navigation)
5. [Daily Operations — Operator](#5-daily-operations--operator)
   - 5.1 Opening a Shift
   - 5.2 Pump Readings
   - 5.3 Oils & Lubricants
   - 5.4 Client Sales (Credit)
   - 5.5 Card Payments
   - 5.6 POS Transactions
   - 5.7 Expenses
   - 5.8 Tank Dips
   - 5.9 Cash Count & Reconciliation
   - 5.10 Finalising the DSR
6. [Accounts — Manager/Owner](#6-accounts--managerowner)
   - 6.1 Credit Customers
   - 6.2 Customer Statement
   - 6.3 Recording a Payment
   - 6.4 Payments List
   - 6.5 Card Reconciliations
   - 6.6 POS Account
7. [DSR Approval — Manager/Owner](#7-dsr-approval--managerowner)
8. [Deliveries](#8-deliveries)
9. [Reports](#9-reports)
10. [Station Settings — Owner](#10-station-settings--owner)
    - 10.1 Pumps & Nozzles
    - 10.2 Tanks
    - 10.3 Products
    - 10.4 Fuel Prices
    - 10.5 Shop Products
11. [Key Concepts Explained](#11-key-concepts-explained)
12. [Troubleshooting](#12-troubleshooting)

---

## 1. Introduction

The DSR (Daily Sales Reconciliation) system replaces the legacy desktop application. It records every transaction that occurs at the fuel station during a shift — fuel dispensed from each pump, credit sales, card and POS payments, expenses, oil sales, and tank stock — and produces a reconciled daily report that confirms whether fuel sold matches money collected.

The system is designed so that:

- **Nothing can be silently wrong.** Every variance — fuel stock, cash, or credit balances — is calculated and displayed explicitly.
- **Historical data cannot be altered.** Once a DSR is approved, all records are locked. Corrections go through a formal adjustment process.
- **Everything is traceable.** Every entry records who entered it and when.

---

## 2. Roles and Access

| Role | What they do |
|---|---|
| **Owner** | Full access. Manages station settings, views all reports, approves DSRs. |
| **Manager** | Manages shifts, approves DSRs, manages credit customers and payments. |
| **Operator** | Records daily shift data: meter readings, sales, expenses, dips. Cannot approve DSRs. |

> **Rule:** Operators record data. Managers and Owners verify and approve it.

---

## 3. Logging In

1. Open your browser and go to the system URL (e.g. `http://dsr.test`)
2. You will be taken directly to the login page
3. Enter your **Email** and **Password**
4. Click **Log In**

You will land on the **Dashboard** which shows today's key figures.

> If you forget your password, contact your system administrator to reset it.

---

## 4. Navigation

The sidebar on the left contains all sections:

**Operations**
- Dashboard
- Shifts
- Deliveries

**Accounts**
- Credit Customers
- Payments
- Card Reconciliations
- POS Account

**Reports** *(collapsible)*
- Wet Stock
- Sales Summary
- Variance
- Delivery History
- Credit Statement

**Settings**
- Station Settings

The sidebar can be **pinned open** by clicking the pin icon at the top. Your preference is saved automatically.

---

## 5. Daily Operations — Operator

The core daily workflow follows this sequence:

```
Open Shift → Enter Pump Readings → Record Sales/Expenses → Tank Dips → Cash Count → Finalise DSR
```

### 5.1 Opening a Shift

1. Go to **Shifts** in the sidebar
2. Select today's date using the date picker at the top
3. Click **Open Day Shift** or **Open Night Shift**
4. The shift opens and you are taken to the shift detail page

> **Important:** A day shift and a night shift can exist on the same date. You cannot open two day shifts or two night shifts on the same date.

The shift detail page has tabs across the top:
`Pump Readings | Oils | Shortage Calculation | Client Sales | Cards | POS | Expenses | Sales Summary`

---

### 5.2 Pump Readings

**Tab: Pump Readings**

This is the most critical entry. Every nozzle dispensing fuel must have a closing meter reading recorded.

**To enter a reading:**

1. Click on a nozzle card (e.g. DX1 DIESEL). The nozzle becomes highlighted.
2. Below the nozzle list, a form appears showing:
   - **Opening readings** (auto-filled from the previous shift's closing readings — do not change)
   - **Closing Mechanical** — enter the mechanical counter reading
   - **Closing Electrical** — enter the electrical counter reading
   - **Closing SHS** — enter the SHS (pump revenue odometer) reading
3. Click **Save Reading**

**How opening readings work:**
- When a nozzle is selected, the opening readings appear automatically
- They come from the last recorded closing readings for that nozzle
- If this is the first ever reading for a nozzle, they come from the figures set in Station Settings
- You cannot manually change opening readings — they are derived from the previous shift

**The readings table shows:**

| Column | Meaning |
|---|---|
| METER | Mechanical / Electrical / SHS |
| OPENING (AUTO) | Previous shift's closing (read-only) |
| CLOSING (ENTER) | What you type in |
| SALES | Calculated: closing − opening |

**To clear a reading** (if you entered it incorrectly):
- Click the **Clear Reading** button on the reading row
- This removes the closing values and reverts the nozzle to its opening readings
- Only available while the shift is not locked

> **Tip:** Always enter the electrical reading accurately — this is the primary figure used in the DSR calculation. The mechanical reading is a cross-check.

---

### 5.3 Oils & Lubricants

**Tab: Oils**

For any lubricants or shop products sold during the shift:

1. Select the **product** from the dropdown
2. Enter the **quantity sold**
3. The price is pre-filled from the product settings
4. Click **Add**

The total oil sales value is shown at the bottom of the list and feeds into the Sales Summary.

To remove a line, click the **Delete** (×) button on that row.

---

### 5.4 Client Sales (Credit)

**Tab: Client Sales**

For customers who purchase fuel on credit (on account):

1. Select the **customer** from the dropdown
2. Select the **product** (e.g. Diesel, Unleaded)
3. Enter the **quantity in litres**
4. Enter the **price per litre** (defaults to the current pump price)
5. Enter the vehicle plate if required
6. Click **Add Sale**

The system automatically calculates:
- Total value (qty × price)
- VAT amount (16%)
- WHT amount (1.72%) — if the customer is a withholding VAT agent

To remove a sale, click the **trash icon** on that row.

> **Note:** Credit sales reduce the cash expected in the drawer. The customer's account balance increases and must be settled via a payment.

---

### 5.5 Card Payments

**Tab: Cards**

For sales paid by credit/debit card through a POS terminal:

1. Enter the **Card Name** (e.g. Visa, KCB, Equity)
2. Enter the **Reference** (transaction/slip number)
3. Enter the **Amount**
4. Click **Add**

Card sales are non-cash — they reduce the cash expected in the drawer.

---

### 5.6 POS Transactions

**Tab: POS**

For payments processed through an external POS system:

1. Enter the **Reference**
2. Enter the **Amount**
3. Click **Add**

POS transactions are non-cash.

---

### 5.7 Expenses

**Tab: Expenses**

For any cash paid out during the shift (e.g. cleaning, casual labour, purchases):

1. Enter the **Expense Item** description
2. Enter the **Amount**
3. Click **Add**

Expenses reduce the expected cash in the drawer.

To remove an expense, click the **Delete** (×) button.

> **Important:** All expenses are assumed to be paid in cash. If you pay for something by MPESA or bank transfer, do not record it here — it should be handled separately as a non-cash expense.

---

### 5.8 Tank Dips

**Tab: Shortage Calculation**

Tank dips measure the actual physical fuel in each tank. They are recorded at the start and end of each shift.

**To enter a dip:**

1. Select the **Tank** from the dropdown
2. Select **Opening** or **Closing** dip type
3. Enter the **Dip Volume** in litres
4. Enter the **Pump Test Volume** if applicable
5. Click **Save Dip**

The shortage calculation table shows:
- Opening stock (from previous closing dip)
- Deliveries received
- Expected stock (opening + deliveries − litres sold)
- Actual stock (from closing dip)
- **Variance** (actual − expected) — shown in red if shortage, green if excess

---

### 5.9 Cash Count & Reconciliation

**Tab: Sales Summary**

At the end of the shift, before finalising the DSR, the operator must count the physical cash in the drawer and record:

**Step 1 — Enter MPESA Total**
- In the **MPESA** field, type the total MPESA transactions received during the shift
- This is a non-cash channel and must be entered to correctly calculate expected cash

**Step 2 — Enter Actual Cash Counted**
- Count the physical cash in the drawer
- Enter the total in the **Actual Cash Counted** field

**Step 3 — Click Save Cash Count**

The system will immediately show:

| Line | What it means |
|---|---|
| **Expected Cash in Drawer** | Calculated: fuel cash + oil cash + cash receipts − expenses |
| **Actual Cash Counted** | What you entered |
| **Variance** | Actual − Expected. Positive = surplus. Negative = shortage. |

**Variance colour coding:**

| Colour | Status | Meaning |
|---|---|---|
| 🟢 Green | OK | Variance within acceptable range |
| 🟡 Amber | Warning | Variance is notable — manager should review |
| 🔴 Red | Critical | Significant variance — manager must provide override reason to approve |
| ⚪ Grey | Not Counted | Actual cash not yet entered |

> **The cash count does not have to balance to zero.** Small variances (change, rounding) are normal. Large variances are flagged for review.

---

### 5.10 Finalising the DSR

Once all data has been entered:

1. Click the **Finalise DSR** button at the top of the shift page
2. Confirm the prompt
3. The system generates the DSR:
   - Calculates fuel sales per product
   - Reconciles tank stock
   - Snapshots cash figures
   - Classifies variance status (OK/Warning/Critical)
4. You are redirected to the **DSR view**

> **After finalisation:** The shift status changes from OPEN to CLOSED. The DSR must then be **approved** by a manager or owner to be fully locked.

---

## 6. Accounts — Manager/Owner

### 6.1 Credit Customers

**Accounts → Credit Customers**

Shows all customers with credit accounts, their credit limits, and current running balances.

**To add a new customer:**
1. Click **Add Customer**
2. Fill in: Customer Name, Contact Person, Phone, Email, Address, City
3. Fill in financial details: Credit Limit, PIN Number, VAT Number (if applicable)
4. Tick **Is Withholding VAT Agent** if applicable
5. Enter **Initial Opening Balance** — the balance the customer brought from the legacy system
6. Set **Discount Multiplier** if the customer receives a price discount (e.g. 0.95 = 5% discount)
7. Click **Save**

**To edit a customer:** Click the **Edit** button on the customer row.

**Balance column:** Shows the current outstanding balance (opening balance + sales − payments). A positive balance means the customer owes money.

---

### 6.2 Customer Statement

Click on any customer name to open their full statement.

The statement shows a **combined ledger** — all credit sales and all payments in date order, with a running balance:

| Column | Meaning |
|---|---|
| Date | Transaction date |
| Description | Sale details or payment reference |
| Debit | Amount charged (sale) |
| Credit | Amount received (payment) |
| Balance | Running total after each transaction |

**Date filter:** Use the From/To date fields to filter the statement to a specific period. The brought-forward balance is shown at the top.

**To delete a credit sale:** Click the trash icon on a sale row (only available before DSR is locked).

---

### 6.3 Recording a Payment

From the customer statement page:

1. Click **Add Payment**
2. Fill in:
   - **Payment Date**
   - **Receipt No**
   - **Trans Type**: Receipts / Fuel / LPG / POS / Invoice
   - **Payment Method**: Cash / MPESA / Bank Transfer / Cheque / RTGS / Equity Card
   - **Reference** (cheque number, bank ref, etc.)
   - **Amount**
3. Click **Save Payment**

The customer's balance decreases immediately.

---

### 6.4 Payments List

**Accounts → Payments**

Shows all payments received across all customers in one list.

Columns: Date | Client | Receipt No | Ref/Chq | Trans Type | Method | Amount

You can **edit** or **delete** any payment from this page (before DSR locking).

---

### 6.5 Card Reconciliations

**Accounts → Card Reconciliations**

Used to reconcile card payment batches submitted to the bank.

**To create a reconciliation:**
1. Click **New Reconciliation**
2. Enter: Card Name, Batch Reference, Recon Date
3. Click **Save**

**To add transaction lines:**
1. Click on the reconciliation row to open it
2. In the lines table, enter: Date, Reference, Amount
3. Click **Add Line**

The total of all lines is shown at the bottom. This should match the batch total from the bank.

---

### 6.6 POS Account

**Accounts → POS Account**

Shows all POS transactions recorded across all shifts in a single list.

Columns: Date | Reference | Amount | Grand Total

---

## 7. DSR Approval — Manager/Owner

After an operator finalises a DSR, it must be reviewed and approved by a manager or owner.

**To approve a DSR:**

1. Go to **Shifts**, find the shift, click **View DSR**
   — or go to **DSR** list and click on the DSR
2. Review the DSR figures:
   - Product line items (litres sold, revenue, stock variance)
   - Cash reconciliation
   - Adjustments (if any)
3. Check the **variance status badge** (OK / WARNING / CRITICAL)

**If status is OK or WARNING:**
- Click **Approve & Lock**
- Confirm the prompt

**If status is CRITICAL:**
- A red **Override & Approve** button appears
- You must type an **override reason** explaining why you are approving despite the variance
- Click **Confirm Override & Approve**

**What happens on approval:**
- The DSR is locked (cannot be changed)
- The shift is locked
- All meter readings, tank dips, credit sales, and payments for this shift are locked
- Fuel sales are written to the financial ledger

> **Once a DSR is locked it cannot be unlocked.** Corrections must be recorded as Adjustments.

**To add an adjustment to a locked DSR:**
1. Open the DSR
2. Click **Add Adjustment**
3. Enter: Adjustment Type, Reason, Original Value, Corrected Value
4. Click **Save**

Adjustments are audit entries — they record what was wrong and what the correct value is, but they do not alter the original data.

---

## 8. Deliveries

**Operations → Deliveries**

Record every fuel delivery received at the station.

**To record a delivery:**
1. Click **New Delivery**
2. Fill in:
   - **Date**
   - **Product** (Diesel, Unleaded, etc.)
   - **Tank** receiving the delivery
   - **Supplier Name**
   - **Truck Registration**
   - **Waybill Number**
   - **Delivery Quantity** (litres)
   - **Tank Dip Before** and **Tank Dip After** delivery
   - **Notes** (optional)
3. Click **Save**

The delivery variance (dip after − dip before − delivery quantity) is calculated and shown.

Deliveries are also linked to a shift. They appear in the DSR for that shift and are included in the stock reconciliation.

---

## 9. Reports

All reports are accessed from the **Reports** section in the sidebar.

### Wet Stock Report

Shows tank stock levels over a date range:
- Opening stock, deliveries, sales, closing stock per tank per day
- Use this to track slow leaks or dip inaccuracies over time

### Sales Summary

Summarises sales by product over a date range:
- Total litres sold, total revenue, credit vs cash split

### Variance Report

Shows the fuel stock variance (shortage/excess) per product per shift.
- Cumulative variance percentage tracks drift over time
- Use this for regulatory compliance and loss detection

### Delivery History

Lists all deliveries over a date range, filterable by product or supplier.

### Credit Statement

Generates a printable statement for any credit customer over a date range.

---

## 10. Station Settings — Owner

**Settings → Station Settings**

This section is for initial setup and ongoing maintenance of the station's configuration.

### 10.1 Pumps & Nozzles

Each tab shows nozzles grouped by product (Diesel, Unleaded, etc.).

**To add a nozzle:**
1. Click **Add Nozzle**
2. Enter: Product, Nozzle Reference (e.g. DX1), Full Name, Default Tank
3. Click **OK**

**To edit a nozzle:**
1. Click the nozzle name
2. The **Change Nozzle** dialog opens
3. You can edit the nozzle details

**Opening Readings panel** (blue border):
- Enter the mechanical, electrical, and SHS readings to set the starting point for this nozzle
- These become the opening readings when the next shift uses this nozzle
- **Only edit these if the readings are wrong** — in normal operation, opening readings are automatically carried forward from the previous shift

### 10.2 Tanks

**To add a tank:**
1. Click the **Tanks** tab
2. Click **Add Tank**
3. Enter: Product, Tank Name, Capacity
4. Tick **Complex Tank** if the tank has two dip measurement points (Dip 1 + Dip 2)
5. Click **OK**

### 10.3 Products

Lists the fuel products available at the station (Diesel, Unleaded, V-Power, Kerosene, etc.).

**To add a product:**
1. Click **Add Product**
2. Enter the product name and type
3. Click **Save**

### 10.4 Fuel Prices

Shows the current price per litre for each product and the history of price changes.

**To change a price:**
1. Click **Update Price** next to the product
2. Enter the new price per litre and the effective date
3. Click **Save**

> **Important:** Always set a price before opening a shift. If no price is set, fuel revenue will calculate as zero.

### 10.5 Shop Products

Lists lubricants, oils, and other shop products that can be sold per shift.

**To add a product:**
1. Click **Add Product**
2. Enter: Name, Unit, Price, Opening Stock
3. Click **Save**

**Stock management:** Each product shows current stock. Use the **Add Stock** button to record when new stock arrives.

---

## 11. Key Concepts Explained

### How Cash is Calculated

The system calculates **Expected Cash** as follows:

```
Fuel Cash Sales    = Total Fuel Revenue
                   − Credit Sales
                   − Card Payments
                   − POS Transactions
                   − MPESA Received

Oil Cash Sales     = Total Oil/Shop Sales (assumed all cash)

Cash Received      = Customer payments received today in cash

Less: Cash Expenses

Expected Cash      = Fuel Cash Sales + Oil Cash Sales + Cash Received − Cash Expenses
```

The operator then physically counts the cash and enters **Actual Cash Counted**. The **Variance** is the difference.

### How Stock Variance is Calculated

```
Opening Stock     = Previous shift's closing dip
+ Deliveries      = Fuel delivered during this shift
− Litres Sold     = From meter readings (electrical meter)
= Expected Stock

Actual Stock      = Closing tank dip reading

Variance          = Actual − Expected
Negative          = Shortage (fuel dispensed but not measured in tank)
Positive          = Excess (more fuel in tank than expected)
```

### What Locking Means

When a DSR is **approved**, the shift is **locked**:
- No meter readings can be added, changed, or deleted
- No credit sales can be added or deleted
- No payments can be edited
- No tank dips can be changed

The shift page will show a red **LOCKED** banner. All forms are hidden.

**After locking**, any corrections must be recorded as **Adjustments** on the DSR page.

### Credit Customer Balance

```
Balance = Initial Opening Balance + Total Credit Sales − Total Payments
```

The initial opening balance is the amount the customer owed when they were first entered into this system (migrated from the previous system).

### Shift Status

| Status | Meaning |
|---|---|
| **OPEN** | Shift is active — data can be entered |
| **CLOSED** | DSR has been generated — awaiting approval |
| **LOCKED** | DSR has been approved — all data is read-only |

---

## 12. Troubleshooting

### "Opening readings show as 0.0"

**Cause:** The nozzle has no previous closing readings and no opening readings set in Station Settings.

**Fix:** Go to Station Settings → Pumps, click the nozzle, and enter the correct mechanical, electrical, and SHS readings in the **Opening Readings** panel. These will be used as the opening for the next shift.

### "Cash variance is CRITICAL"

**Cause:** The difference between expected and actual cash exceeds the threshold (KES 2,000 or 3%).

**Action:** Recheck all entries:
- Are all credit sales recorded?
- Are all card/POS/MPESA amounts entered?
- Is the MPESA total correct?
- Are any expenses missing or double-entered?
- Recount the cash

If the variance is correct and understood (e.g. known short-change), a manager can approve with an override reason.

### "Cannot delete a sale/payment"

**Cause:** The DSR for this shift has been approved and locked.

**Action:** The data cannot be changed. Record an Adjustment on the DSR page instead.

### "Cannot find a customer in the credit sales dropdown"

**Cause:** The customer may be marked as inactive, or not yet created.

**Action:** Go to Accounts → Credit Customers and check if the customer exists and is marked **Active**.

### "No price set" warning in cash reconciliation

**Cause:** No fuel price has been recorded for a product on this shift's date.

**Action:** Go to Station Settings → Fuel Prices and add the correct price with the right effective date.

### "Shift already exists" error when opening

**Cause:** A shift of the same type (day/night) already exists for this date.

**Action:** Go to Shifts, select the correct date, and open the existing shift instead of creating a new one.

### MPESA amount not saving

**Cause:** MPESA is saved only when you click **Save Cash Count**. Do not navigate away before clicking the button.

**Action:** Enter the MPESA amount, then enter the actual cash count, then click **Save Cash Count**.

---

*For technical support or to report issues, contact your system administrator.*

*System built on Laravel 12 + Vue 3 + Inertia.js*
