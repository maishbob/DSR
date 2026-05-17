<?php

namespace App\Console\Commands;

use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Imports daily expenses from ForDaiExp.csv (Clarion export).
 *
 * COLUMNS IMPORTED
 *   Dsr Id      → shift_id  (looked up via shifts.dsr_number)
 *   Dsr Date    → fallback date if shift not found
 *   Expenseitem → expense_item
 *   Amount      → amount
 *
 * COLUMNS NOT IMPORTED
 *   Rec No    — legacy internal PK
 *   Id        — legacy expense ID
 *   Serialno  — always 0, not stored
 */
class ImportLegacyExpenses extends Command
{
    protected $signature = 'import:legacy-expenses
        {station_id : The station ID to import into}
        {csv_path : Path to ForDaiExp.csv}
        {--dry-run : Preview without writing}';

    protected $description = 'Import legacy daily expenses from the Clarion ForDaiExp.csv export';

    private int $stationId;
    private bool $dryRun;

    private int $created = 0;
    private int $skipped = 0;
    private int $rowsSkipped = 0;

    private array $shiftCache = [];

    public function handle(): int
    {
        $this->stationId = (int) $this->argument('station_id');
        $this->dryRun    = (bool) $this->option('dry-run');
        $csvPath         = $this->argument('csv_path');

        if (! file_exists($csvPath)) {
            $this->error("File not found: {$csvPath}");
            return self::FAILURE;
        }

        if ($this->dryRun) {
            $this->warn('DRY RUN — nothing will be written.');
        }

        $this->preloadShifts();

        $rows = $this->readCsv($csvPath);
        $this->info(sprintf('Processing %d rows…', count($rows)));
        $bar = $this->output->createProgressBar(count($rows));

        if (! $this->dryRun) {
            DB::beginTransaction();
        }

        try {
            foreach ($rows as $row) {
                $bar->advance();
                $this->processRow($row);
            }

            if (! $this->dryRun) {
                DB::commit();
            }
        } catch (\Throwable $e) {
            if (! $this->dryRun) {
                DB::rollBack();
            }
            $this->error("Import failed: {$e->getMessage()}");
            throw $e;
        }

        $bar->finish();
        $this->newLine();

        $this->table(['Metric', 'Count'], [
            ['Expenses created', $this->created],
            ['Rows skipped (no shift match)', $this->skipped],
            ['Rows skipped (parse errors)', $this->rowsSkipped],
        ]);

        if ($this->dryRun) {
            $this->warn('DRY RUN complete — nothing was written.');
        } else {
            $this->info('Import complete.');
        }

        return self::SUCCESS;
    }

    private function preloadShifts(): void
    {
        Shift::where('station_id', $this->stationId)
            ->whereNotNull('dsr_number')
            ->get()
            ->each(function (Shift $s) {
                $this->shiftCache[(string) $s->dsr_number] = $s->id;
            });

        $this->info(sprintf('Loaded %d shifts from database.', count($this->shiftCache)));
    }

    private function processRow(array $row): void
    {
        $dsrId       = $this->cleanInteger(trim($row['Dsr Id'] ?? ''));
        $dateStr     = trim($row['Dsr Date'] ?? '');
        $expenseItem = trim($row['Expenseitem'] ?? '');
        $amount      = $this->cleanAmount($row['Amount'] ?? '0');

        if ($expenseItem === '' || $amount == 0) {
            $this->rowsSkipped++;
            return;
        }

        $shiftId = $this->shiftCache[$dsrId] ?? null;

        if (! $shiftId) {
            $this->warn("  No shift for DSR #{$dsrId} ({$expenseItem}) — skipping.");
            $this->skipped++;
            return;
        }

        $date = $this->parseLegacyDate($dateStr);

        if ($this->dryRun) {
            $this->created++;
            return;
        }

        DB::table('expenses')->insert([
            'shift_id'     => $shiftId,
            'expense_item' => $expenseItem,
            'amount'       => abs($amount),
            'entered_by'   => null,
            'created_at'   => $date?->startOfDay() ?? now(),
            'updated_at'   => now(),
        ]);

        $this->created++;
    }

    private function parseLegacyDate(string $dateStr): ?Carbon
    {
        $dateStr = trim($dateStr);
        try {
            return Carbon::createFromFormat('n/j/Y', $dateStr)->startOfDay();
        } catch (\Exception) {
            foreach (['m/d/Y', 'Y-m-d', 'd/m/Y'] as $fmt) {
                try {
                    return Carbon::createFromFormat($fmt, $dateStr)->startOfDay();
                } catch (\Exception) {
                    continue;
                }
            }
            return null;
        }
    }

    private function cleanAmount(string $value): float
    {
        return (float) preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $value));
    }

    private function cleanInteger(string $value): string
    {
        return preg_replace('/[^0-9]/', '', $value);
    }

    private function readCsv(string $path): array
    {
        $rows   = [];
        $handle = fopen($path, 'r');

        if (! $handle) {
            $this->error("Cannot open: {$path}");
            return [];
        }

        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);
            return [];
        }

        $header = array_map(fn ($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)), $header);

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
