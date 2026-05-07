<?php

namespace App\Console\Commands;

use App\Models\CreditCustomer;
use App\Models\CreditSale;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyData extends Command
{
    protected $signature = 'import:legacy
        {station_id : The station ID to import into}
        {clients_csv : Path to clients.csv}
        {transactions_csv : Path to clientTransactions.csv}
        {--dry-run : Preview what would be imported without writing to the database}';

    protected $description = 'Import credit customers and transactions from legacy Clarion CSV exports';

    private int $stationId;
    private bool $dryRun;

    // Counters
    private int $customersCreated = 0;
    private int $customersSkipped = 0;
    private int $salesCreated = 0;
    private int $paymentsCreated = 0;
    private int $shiftsCreated = 0;
    private int $rowsSkipped = 0;

    // Lookup caches
    private array $customerMap = [];  // lowercase name => credit_customer_id
    private array $shiftCache = [];   // "Y-m-d" => shift_id
    private ?int $defaultProductId = null;

    public function handle(): int
    {
        $this->stationId = (int) $this->argument('station_id');
        $this->dryRun = (bool) $this->option('dry-run');

        $clientsPath = $this->argument('clients_csv');
        $transactionsPath = $this->argument('transactions_csv');

        if (! file_exists($clientsPath)) {
            $this->error("Clients CSV not found: {$clientsPath}");
            return self::FAILURE;
        }
        if (! file_exists($transactionsPath)) {
            $this->error("Transactions CSV not found: {$transactionsPath}");
            return self::FAILURE;
        }

        // Resolve a default product for credit sales (first active product for this station)
        $this->defaultProductId = Product::where('station_id', $this->stationId)
            ->where('is_active', true)
            ->value('id');

        if (! $this->defaultProductId) {
            $this->error("No active product found for station {$this->stationId}. Create at least one product first.");
            return self::FAILURE;
        }

        if ($this->dryRun) {
            $this->warn('DRY RUN — no data will be written.');
        }

        $this->info('Importing clients...');
        $this->importClients($clientsPath);

        $this->info('Importing transactions...');
        $this->importTransactions($transactionsPath);

        $this->newLine();
        $this->table(['Metric', 'Count'], [
            ['Customers created', $this->customersCreated],
            ['Customers skipped (duplicate)', $this->customersSkipped],
            ['Legacy shifts created', $this->shiftsCreated],
            ['Credit sales created', $this->salesCreated],
            ['Payments created', $this->paymentsCreated],
            ['Rows skipped (errors)', $this->rowsSkipped],
        ]);

        if ($this->dryRun) {
            $this->warn('DRY RUN complete — nothing was written.');
        } else {
            $this->info('Import complete.');
        }

        return self::SUCCESS;
    }

    /**
     * CSV columns: Rec No, Id, Acno, Name, Address, Email Address, City, Fax,
     * Telephone, Contact, Pin, Vat, ..., Broughtforward, Openingbalance, Balance,
     * ..., Creditlimit, ..., Discountmultiplier, Withholdingvat Agent
     */
    private function importClients(string $path): void
    {
        $rows = $this->readCsv($path);
        $bar = $this->output->createProgressBar(count($rows));

        foreach ($rows as $row) {
            $bar->advance();

            $name = trim($row['Name'] ?? '');
            if ($name === '') {
                $this->rowsSkipped++;
                continue;
            }

            // Check for existing customer (by name within this station)
            $existing = CreditCustomer::where('station_id', $this->stationId)
                ->whereRaw('LOWER(customer_name) = ?', [strtolower($name)])
                ->first();

            if ($existing) {
                $this->customerMap[strtolower($name)] = $existing->id;
                $this->customersSkipped++;
                continue;
            }

            // Use Broughtforward as the opening balance (legacy brought-forward figure)
            $openingBalance = $this->cleanAmount($row['Broughtforward'] ?? $row['Openingbalance'] ?? '0');
            $creditLimit = $this->cleanAmount($row['Creditlimit'] ?? '0');
            $discountMultiplier = $this->cleanAmount($row['Discountmultiplier'] ?? '0');
            $isWhAgent = (int) trim($row['Withholdingvat Agent'] ?? '0');

            if ($this->dryRun) {
                $this->customerMap[strtolower($name)] = -1;
                $this->customersCreated++;
                continue;
            }

            $customer = CreditCustomer::create([
                'station_id'              => $this->stationId,
                'customer_name'           => $name,
                'contact'                 => trim($row['Contact'] ?? ''),
                'phone'                   => trim($row['Telephone'] ?? ''),
                'email'                   => trim($row['Email Address'] ?? ''),
                'address'                 => trim($row['Address'] ?? ''),
                'city'                    => trim($row['City'] ?? ''),
                'pin'                     => trim($row['Pin'] ?? ''),
                'vat_number'              => trim($row['Vat'] ?? ''),
                'is_withholding_vat_agent' => $isWhAgent === 1,
                'credit_limit'            => abs($creditLimit),
                'discount_multiplier'     => $discountMultiplier,
                'initial_opening_balance' => $openingBalance,
                'is_active'               => true,
            ]);

            $this->customerMap[strtolower($name)] = $customer->id;
            $this->customersCreated++;
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * CSV columns: Rec No, Id, Dsrid, Serial No, Client Id, Trans Type,
     * Client Name, Dsr Date, Product No, Product Name, Invoice No, Litres,
     * Receipt No, Chq No, Amount, Invoice Amount, Discount, Vat Amount,
     * Withholding Vat Amount
     */
    private function importTransactions(string $path): void
    {
        $rows = $this->readCsv($path);
        $bar = $this->output->createProgressBar(count($rows));

        // Pre-load existing legacy shifts for this station
        Shift::where('station_id', $this->stationId)
            ->where('shift_type', 'day')
            ->where('status', 'locked')
            ->whereNull('opened_by')
            ->get()
            ->each(function (Shift $s) {
                $this->shiftCache[$s->shift_date->format('Y-m-d')] = $s->id;
            });

        if (! $this->dryRun) {
            DB::beginTransaction();
        }

        try {
            foreach ($rows as $row) {
                $bar->advance();
                $this->processTransactionRow($row);
            }

            if (! $this->dryRun) {
                DB::commit();
            }
        } catch (\Throwable $e) {
            if (! $this->dryRun) {
                DB::rollBack();
            }
            $this->error("Transaction import failed: {$e->getMessage()}");
            throw $e;
        }

        $bar->finish();
        $this->newLine();
    }

    private function processTransactionRow(array $row): void
    {
        $clientName = trim($row['Client Name'] ?? '');
        $transType = trim($row['Trans Type'] ?? '');
        $dateStr = trim($row['Dsr Date'] ?? '');
        $amount = $this->cleanAmount($row['Amount'] ?? '0');
        $receiptNo = trim($row['Receipt No'] ?? '');
        $chqNo = trim($row['Chq No'] ?? '');
        $invoiceNo = trim($row['Invoice No'] ?? '');
        $litres = $this->cleanAmount($row['Litres'] ?? '0');
        $vatAmount = $this->cleanAmount($row['Vat Amount'] ?? '0');
        $whtAmount = $this->cleanAmount($row['Withholding Vat Amount'] ?? '0');

        if ($clientName === '' || $dateStr === '' || $amount == 0) {
            $this->rowsSkipped++;
            return;
        }

        // Resolve customer
        $customerId = $this->resolveCustomer($clientName);
        if (! $customerId) {
            $this->warn("  Skipping row — unknown customer: {$clientName}");
            $this->rowsSkipped++;
            return;
        }

        // Parse the legacy date (M/DD/YYYY or M/D/YYYY, often space-prefixed)
        $date = $this->parseLegacyDate($dateStr);
        if (! $date) {
            $this->warn("  Skipping row — invalid date: {$dateStr}");
            $this->rowsSkipped++;
            return;
        }

        $transTypeLower = strtolower($transType);

        if ($transTypeLower === 'receipts' || $transTypeLower === 'receipt') {
            // Payment — use Chq No as the reference (contains MP- codes, cheque nums, etc.)
            $this->createPayment($customerId, $date, $amount, $receiptNo, $chqNo);
        } elseif (in_array($transTypeLower, ['fuel', 'oil', 'lpg'])) {
            $this->createCreditSale($customerId, $date, $amount, $transTypeLower, $invoiceNo, $litres, $vatAmount, $whtAmount);
        } else {
            $this->warn("  Unknown trans type '{$transType}' for {$clientName} — treating as credit sale.");
            $this->createCreditSale($customerId, $date, $amount, 'fuel', $invoiceNo, $litres, $vatAmount, $whtAmount);
        }
    }

    private function createPayment(int $customerId, Carbon $date, float $amount, string $receiptNo, string $chqNo): void
    {
        // Determine payment method from Chq No field
        $paymentMethod = 'cash';
        $reference = $chqNo;

        if (str_starts_with(strtoupper($chqNo), 'MP-')) {
            $paymentMethod = 'mpesa';
        } elseif ($chqNo !== '' && $chqNo !== '0') {
            // Has a cheque number that's not an MPESA code
            $paymentMethod = 'cheque';
        }

        if ($this->dryRun) {
            $this->paymentsCreated++;
            return;
        }

        Payment::create([
            'credit_customer_id' => $customerId,
            'station_id'         => $this->stationId,
            'payment_date'       => $date->format('Y-m-d'),
            'receipt_no'         => $receiptNo ?: null,
            'trans_type'         => 'receipts',
            'amount'             => abs($amount),
            'payment_method'     => $paymentMethod,
            'reference'          => $reference ?: null,
            'notes'              => 'Legacy import',
            'is_locked'          => true,
        ]);

        $this->paymentsCreated++;
    }

    private function createCreditSale(
        int $customerId,
        Carbon $date,
        float $amount,
        string $type,
        string $invoiceNo,
        float $litres,
        float $vatAmount,
        float $whtAmount,
    ): void {
        $shiftId = $this->getOrCreateLegacyShift($date);

        if ($this->dryRun) {
            $this->salesCreated++;
            return;
        }

        $saleType = match ($type) {
            'oil'  => 'oil',
            'lpg'  => 'other',
            default => 'fuel',
        };

        $absAmount = abs($amount);

        // Use legacy VAT/WHT if provided, otherwise compute
        $computedVat = $vatAmount != 0 ? abs($vatAmount) : round($absAmount - $absAmount / 1.16, 2);
        $computedWht = $whtAmount != 0 ? abs($whtAmount) : round($absAmount * 0.0172, 2);

        // Derive price from litres if available
        $qty = abs($litres);
        $price = ($qty > 0) ? round($absAmount / $qty, 4) : 0;

        // Insert directly to avoid the saving() boot recalculating totals
        DB::table('credit_sales')->insert([
            'credit_customer_id' => $customerId,
            'product_id'         => $this->defaultProductId,
            'shift_id'           => $shiftId,
            'debit_note'         => $invoiceNo ?: null,
            'type'               => $saleType,
            'quantity'           => $qty,
            'price_applied'      => $price,
            'total_value'        => $absAmount,
            'vat_amount'         => $computedVat,
            'wht_amount'         => $computedWht,
            'vehicle_plate'      => null,
            'notes'              => 'Legacy import',
            'entered_by'         => null,
            'is_locked'          => true,
            'created_at'         => $date->copy()->startOfDay(),
            'updated_at'         => now(),
        ]);

        $this->salesCreated++;
    }

    private function getOrCreateLegacyShift(Carbon $date): int
    {
        $key = $date->format('Y-m-d');

        if (isset($this->shiftCache[$key])) {
            return $this->shiftCache[$key];
        }

        if ($this->dryRun) {
            $this->shiftCache[$key] = -1;
            $this->shiftsCreated++;
            return -1;
        }

        $shift = Shift::firstOrCreate(
            [
                'station_id' => $this->stationId,
                'shift_date' => $key,
                'shift_type' => 'day',
            ],
            [
                'opened_at' => $date->copy()->startOfDay(),
                'closed_at' => $date->copy()->endOfDay(),
                'status'    => 'locked',
            ]
        );

        if ($shift->wasRecentlyCreated) {
            $this->shiftsCreated++;
        }

        $this->shiftCache[$key] = $shift->id;
        return $shift->id;
    }

    private function resolveCustomer(string $name): ?int
    {
        $key = strtolower(trim($name));

        if (isset($this->customerMap[$key])) {
            return $this->customerMap[$key] > 0 ? $this->customerMap[$key] : null;
        }

        // Try to find in DB (may have been created in a prior import run)
        $customer = CreditCustomer::where('station_id', $this->stationId)
            ->whereRaw('LOWER(customer_name) = ?', [$key])
            ->first();

        if ($customer) {
            $this->customerMap[$key] = $customer->id;
            return $customer->id;
        }

        // Auto-create stub customer from transactions (exists in transactions but not clients.csv)
        if (! $this->dryRun) {
            $customer = CreditCustomer::create([
                'station_id'              => $this->stationId,
                'customer_name'           => trim($name),
                'initial_opening_balance' => 0,
                'is_active'               => true,
            ]);
            $this->customerMap[$key] = $customer->id;
            $this->customersCreated++;
            $this->warn("  Auto-created customer stub: {$name}");
            return $customer->id;
        }

        return null;
    }

    private function parseLegacyDate(string $dateStr): ?Carbon
    {
        // Clarion exports: " 5/01/2020" (space-prefixed M/DD/YYYY)
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
        // Remove commas, currency symbols, spaces — keep digits, dots, minus
        $cleaned = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $value));
        return (float) $cleaned;
    }

    private function readCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');

        if (! $handle) {
            $this->error("Cannot open file: {$path}");
            return [];
        }

        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);
            return [];
        }

        // Clean BOM and whitespace from headers
        $header = array_map(function ($h) {
            return trim(preg_replace('/^\xEF\xBB\xBF/', '', $h));
        }, $header);

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) !== count($header)) {
                continue;
            }
            $rows[] = array_combine($header, $data);
        }

        fclose($handle);
        return $rows;
    }
}
