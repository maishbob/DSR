<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Imports card reconciliations from cardrecons.csv (Clarion export).
 *
 * Each CSV row becomes one card_recons record plus one card_recon_lines row
 * carrying the total amount (the legacy system did not store individual line items).
 *
 * COLUMNS IMPORTED
 *   Cardname    → card_recons.card_name
 *   Batchref    → card_recons.batch_ref
 *   Recondate   → card_recons.recon_date
 *   Totalamount → card_recon_lines.amount  (stored as a single summary line)
 *
 * COLUMNS NOT IMPORTED
 *   Rec No        — legacy internal PK
 *   Cardirecondid — legacy recon ID
 *   Cardid        — legacy card type ID, not stored
 *
 * NOTE: cardrecons.csv uses TAB as delimiter (as exported by Clarion Viewer).
 */
class ImportLegacyCardRecons extends Command
{
    protected $signature = 'import:legacy-card-recons
        {station_id : The station ID to import into}
        {csv_path : Path to cardrecons.csv}
        {--dry-run : Preview without writing}';

    protected $description = 'Import legacy card reconciliations from the Clarion cardrecons.csv export';

    private int $stationId;
    private bool $dryRun;

    private int $reconsCreated = 0;
    private int $skipped = 0;
    private int $rowsSkipped = 0;

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
            ['Card recons created', $this->reconsCreated],
            ['Rows skipped (duplicate)', $this->skipped],
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
        $cardName   = trim($row['Cardname'] ?? '');
        $batchRef   = trim($row['Batchref'] ?? '');
        $reconDate  = trim($row['Recondate'] ?? '');
        $totalAmount = $this->cleanAmount($row['Totalamount'] ?? '0');

        if ($cardName === '' || $reconDate === '') {
            $this->rowsSkipped++;
            return;
        }

        $date = $this->parseLegacyDate($reconDate);
        if (! $date) {
            $this->warn("  Invalid date '{$reconDate}' — skipping.");
            $this->rowsSkipped++;
            return;
        }

        if ($this->dryRun) {
            $this->reconsCreated++;
            return;
        }

        // Skip exact duplicates (same card, batch, date already imported)
        $exists = DB::table('card_recons')
            ->where('station_id', $this->stationId)
            ->where('card_name', $cardName)
            ->where('batch_ref', $batchRef ?: null)
            ->where('recon_date', $date->format('Y-m-d'))
            ->exists();

        if ($exists) {
            $this->skipped++;
            return;
        }

        $reconId = DB::table('card_recons')->insertGetId([
            'station_id' => $this->stationId,
            'card_name'  => $cardName,
            'batch_ref'  => $batchRef ?: null,
            'recon_date' => $date->format('Y-m-d'),
            'created_by' => null,
            'created_at' => $date->startOfDay(),
            'updated_at' => now(),
        ]);

        // Store the total as a single summary line
        DB::table('card_recon_lines')->insert([
            'card_recon_id' => $reconId,
            'trans_date'    => $date->format('Y-m-d'),
            'ref'           => 'Legacy total',
            'amount'        => abs($totalAmount),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->reconsCreated++;
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

    private function readCsv(string $path): array
    {
        $rows   = [];
        $handle = fopen($path, 'r');

        if (! $handle) {
            $this->error("Cannot open: {$path}");
            return [];
        }

        // Detect delimiter from first line
        $firstLine = fgets($handle);
        rewind($handle);
        $sep = str_contains($firstLine, "\t") ? "\t" : ",";

        $header = fgetcsv($handle, 0, $sep);
        if (! $header) {
            fclose($handle);
            return [];
        }

        $header = array_map(fn ($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)), $header);

        while (($data = fgetcsv($handle, 0, $sep)) !== false) {
            if (count($data) !== count($header)) {
                continue;
            }
            $rows[] = array_combine($header, $data);
        }

        fclose($handle);
        return $rows;
    }
}
