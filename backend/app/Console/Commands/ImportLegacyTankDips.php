<?php

namespace App\Console\Commands;

use App\Models\Shift;
use App\Models\Tank;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Imports opening tank dip readings from fctshortdaily.csv (Clarion export).
 *
 * COLUMNS IMPORTED
 *   Tankname         → tank_id  (looked up by tank name)
 *   Dailyrecordid    → shift_id (looked up via shifts.dsr_number)
 *   Openingdipstock  → dip_volume for dip_type='opening'
 *   Openingdipstock2 → second opening dip for complex tanks (dip_type='opening', stored separately)
 *
 * COLUMNS NOT IMPORTED (CSV export was truncated at column 16)
 *   Dipstock / Dipstock2    — closing dip volumes: MISSING from export
 *   Pumptest                — pump test litres: MISSING from export
 *   Sales, Closingstock, Shortage, Excess, Variancelitrs, Cumulativevariancepc — MISSING
 *
 * ACTION REQUIRED: Re-export fctshortdaily.tps from Clarion to get all 27 columns.
 * The current export stopped at column 16 (Truckreg). Once you have the full CSV,
 * run import:legacy-tank-dips-closing to add closing dips and pump test volumes.
 */
class ImportLegacyTankDips extends Command
{
    protected $signature = 'import:legacy-tank-dips
        {station_id : The station ID to import into}
        {csv_path : Path to fctshortdaily.csv}
        {--dry-run : Preview without writing}';

    protected $description = 'Import legacy opening tank dip readings from fctshortdaily.csv (opening dips only — CSV is truncated)';

    private int $stationId;
    private bool $dryRun;

    private int $created = 0;
    private int $skipped = 0;
    private int $rowsSkipped = 0;

    // tank name (lowercase) => tank_id
    private array $tankCache = [];
    // dsr_number => shift_id
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

        $this->warn('NOTE: This import covers OPENING dips only. The fctshortdaily.csv export is missing closing dip (Dipstock) and pump test (Pumptest) columns. Re-export from Clarion with all 27 columns to get full data.');

        if ($this->dryRun) {
            $this->warn('DRY RUN — nothing will be written.');
        }

        $this->preloadTanks();
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
            ['Opening dip records created', $this->created],
            ['Rows skipped (no tank/shift match)', $this->skipped],
            ['Rows skipped (parse errors)', $this->rowsSkipped],
        ]);

        if ($this->dryRun) {
            $this->warn('DRY RUN complete — nothing was written.');
        } else {
            $this->info('Import complete.');
        }

        return self::SUCCESS;
    }

    private function preloadTanks(): void
    {
        Tank::where('station_id', $this->stationId)
            ->get()
            ->each(function (Tank $t) {
                // Index by the full combined name (e.g. "DIESEL TANK 1")
                $this->tankCache[strtolower($t->tank_name)] = $t->id;
            });

        $this->info(sprintf('Loaded %d tanks from database.', count($this->tankCache)));
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
        $tankName  = trim($row['Tankname'] ?? '');
        $dsrId     = $this->cleanInteger(trim($row['Dailyrecordid'] ?? ''));
        $openDip   = $this->cleanAmount($row['Openingdipstock'] ?? '0');
        $openDip2  = $this->cleanAmount($row['Openingdipstock2'] ?? '0');

        if ($tankName === '' || $dsrId === '') {
            $this->rowsSkipped++;
            return;
        }

        $tankId  = $this->tankCache[strtolower($tankName)] ?? null;
        $shiftId = $this->shiftCache[$dsrId] ?? null;

        if (! $tankId) {
            $this->warn("  Unknown tank '{$tankName}' — skipping.");
            $this->skipped++;
            return;
        }

        if (! $shiftId) {
            $this->warn("  No shift for DSR #{$dsrId} — skipping.");
            $this->skipped++;
            return;
        }

        if ($this->dryRun) {
            $this->created++;
            return;
        }

        // Check for existing opening dip to avoid duplicates
        $exists = DB::table('tank_dips')
            ->where('tank_id', $tankId)
            ->where('shift_id', $shiftId)
            ->where('dip_type', 'opening')
            ->exists();

        if ($exists) {
            $this->skipped++;
            return;
        }

        // Determine dip volume: for complex tanks, sum both compartments
        $dipVolume = $openDip + $openDip2;

        DB::table('tank_dips')->insert([
            'tank_id'          => $tankId,
            'shift_id'         => $shiftId,
            'dip_type'         => 'opening',
            'dip_volume'       => $dipVolume,
            'pump_test_volume' => 0,  // not available in truncated export
            'entered_by'       => null,
            'is_locked'        => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $this->created++;
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
