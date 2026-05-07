<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

function parseLegacyDate(string $dateStr): ?Carbon
{
    $dateStr = trim($dateStr);
    try {
        return Carbon::createFromFormat('n/j/Y', $dateStr)->startOfDay();
    } catch (\Exception $e) {
    }
    foreach (['m/d/Y', 'n/d/Y', 'Y-m-d', 'd/m/Y'] as $fmt) {
        try {
            return Carbon::createFromFormat($fmt, $dateStr)->startOfDay();
        } catch (\Exception $e) {
        }
    }
    return null;
}

// Read CSV and group by parsed date, preserving order
$handle = fopen(__DIR__ . '/database/migrations/dailyShifts.csv', 'r');
$header = array_map(fn($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)), fgetcsv($handle));

$byDate = [];
while (($data = fgetcsv($handle)) !== false) {
    if (count($data) !== count($header)) continue;
    $row = array_combine($header, $data);
    $date = parseLegacyDate(trim($row['Date'] ?? ''));
    if (!$date) continue;
    $key = $date->format('Y-m-d');
    $dsr = str_replace(',', '', trim($row['Daily Record Id'] ?? ''));
    // Deduplicate by DSR within date
    $existing_dsrs = array_column($byDate[$key] ?? [], 'dsr_number');
    if (!in_array($dsr, $existing_dsrs)) {
        $byDate[$key][] = ['date' => $date, 'dsr_number' => $dsr];
    }
}
fclose($handle);

$stationId = 1;
$created = 0;
$fixed = 0;

DB::beginTransaction();
foreach ($byDate as $dateKey => $entries) {
    // Only take first two
    $entries = array_slice($entries, 0, 2);

    foreach ($entries as $idx => $entry) {
        $shiftType = $idx === 0 ? 'day' : 'night';

        $existing = DB::table('shifts')
            ->where('station_id', $stationId)
            ->where('shift_date', $dateKey)
            ->where('shift_type', $shiftType)
            ->first();

        if ($existing) {
            if ($existing->dsr_number != $entry['dsr_number']) {
                DB::table('shifts')->where('id', $existing->id)
                    ->update(['dsr_number' => $entry['dsr_number']]);
                $fixed++;
            }
            continue;
        }

        DB::table('shifts')->insert([
            'station_id' => $stationId,
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
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $created++;
    }
}
DB::commit();

echo "Created {$created} missing shifts, fixed {$fixed} dsr_numbers\n";

$day = DB::table('shifts')->where('station_id', $stationId)->where('shift_type', 'day')->count();
$night = DB::table('shifts')->where('station_id', $stationId)->where('shift_type', 'night')->count();
$single = DB::table('shifts')->where('station_id', $stationId)
    ->select('shift_date')->groupBy('shift_date')
    ->having(DB::raw('COUNT(*)'), '=', 1)->count();
echo "Day: {$day}, Night: {$night}, Total: " . ($day + $night) . "\n";
echo "Dates with only 1 shift: {$single}\n";
