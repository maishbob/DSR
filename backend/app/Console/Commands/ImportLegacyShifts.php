<?php

namespace App\Console\Commands;

use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Imports legacy daily shift records from the Clarion DSR export (dailyShifts.csv).
 *
 * COLUMNS IMPORTED
 *   Date            → shift_date  (parsed from space-prefixed M/D/YYYY)
 *   Daily Record Id → dsr_number  (legacy serial, same value as Serial No)
 *   position/day    → shift_type  (1st occurrence of a date = day, 2nd = night)
 *
 * COLUMNS NOT IMPORTED (flagged)
 *   Rec No            — legacy internal PK, no equivalent in shifts
 *   Sup Id            — always 0; no user-ID mapping available
 *   No Of Days Covered — always 1; not stored
 *   Product1–5        — product labels, not stored on the shift row
 *   Fuel Sales Qty1–5 — daily sales quantities by product; belong in a future
 *                       FctStockDailySales importer, not here
 */
class ImportLegacyShifts extends Command
{
    protected $signature = 'import:legacy-shifts
        {station_id : The station ID to import into}
        {shifts_csv : Path to dailyShifts.csv}
        {--dry-run : Preview what would be imported without writing to the database}';

    protected $description = 'Import legacy daily shift records from the Clarion dailyShifts.csv export';

    private int $stationId;
    private bool $dryRun;

    private int $shiftsCreated = 0;
    private int $shiftsSkipped = 0;
    private int $rowsSkipped = 0;

    // Tracks how many shifts have been seen for a given date so we can assign shift_type.
    // "Y-m-d" => count of rows already processed for that date.
    private array $dateCounter = [];

    public function handle(): int
    {
        $this->stationId = (int) $this->argument('station_id');
        $this->dryRun = (bool) $this->option('dry-run');

        $csvPath = $this->argument('shifts_csv');

        if (! file_exists($csvPath)) {
            $this->error("CSV not found: {$csvPath}");
            return self::FAILURE;
        }

        if ($this->dryRun) {
            $this->warn('DRY RUN — no data will be written.');
        }

        $rows = $this->readCsv($csvPath);

        if (empty($rows)) {
            $this->error('No rows found in CSV.');
            return self::FAILURE;
        }

        $this->info(sprintf('Processing %d rows…', count($rows)));
        $bar = $this->output->createProgressBar(count($rows));

        foreach ($rows as $row) {
            $bar->advance();
            $this->processRow($row);
        }

        $bar->finish();
        $this->newLine();

        $this->table(['Metric', 'Count'], [
            ['Shifts created', $this->shiftsCreated],
            ['Shifts skipped (already exists)', $this->shiftsSkipped],
            ['Rows skipped (parse errors)', $this->rowsSkipped],
        ]);

        if ($this->dryRun) {
            $this->warn('DRY RUN complete — nothing was written.');
        } else {
            $this->info('Import complete.');
        }

        return self::SUCCESS;
    }

    private function processRow(array $row): void
    {
        $dateStr = trim($row['Date'] ?? '');
        $dsrNumber = trim($row['Daily Record Id'] ?? $row['Serial No'] ?? '');

        if ($dateStr === '') {
            $this->rowsSkipped++;
            return;
        }

        $date = $this->parseLegacyDate($dateStr);
        if (! $date) {
            $this->warn("  Skipping row — invalid date: {$dateStr}");
            $this->rowsSkipped++;
            return;
        }

        $key = $date->format('Y-m-d');

        // Increment counter for this date to determine shift_type.
        $this->dateCounter[$key] = ($this->dateCounter[$key] ?? 0) + 1;
        $shiftType = $this->dateCounter[$key] === 1 ? 'day' : 'night';

        // Clean dsr_number (the CSV stores it with spaces and commas, e.g. "        3,435")
        $dsrNumber = $this->cleanInteger($dsrNumber);

        if ($this->dryRun) {
            $this->shiftsCreated++;
            return;
        }

        $existing = Shift::where('station_id', $this->stationId)
            ->where('shift_date', $key)
            ->where('shift_type', $shiftType)
            ->first();

        if ($existing) {
            $this->shiftsSkipped++;
            return;
        }

        Shift::create([
            'station_id' => $this->stationId,
            'shift_date' => $key,
            'shift_type' => $shiftType,
            'dsr_number' => $dsrNumber ?: null,
            'opened_at'  => $date->copy()->startOfDay(),
            'closed_at'  => $date->copy()->endOfDay(),
            'status'     => 'locked',
            'opened_by'  => null,
            'closed_by'  => null,
        ]);

        $this->shiftsCreated++;
    }

    private function parseLegacyDate(string $dateStr): ?Carbon
    {
        $dateStr = trim($dateStr);

        try {
            return Carbon::createFromFormat('n/j/Y', $dateStr)->startOfDay();
        } catch (\Exception) {
            foreach (['m/d/Y', 'n/d/Y', 'Y-m-d', 'd/m/Y'] as $fmt) {
                try {
                    return Carbon::createFromFormat($fmt, $dateStr)->startOfDay();
                } catch (\Exception) {
                    continue;
                }
            }
            return null;
        }
    }

    private function cleanInteger(string $value): string
    {
        return preg_replace('/[^0-9]/', '', $value);
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
