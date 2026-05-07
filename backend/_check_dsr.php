<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$updated = Illuminate\Support\Facades\DB::table('shifts')
    ->where('dsr_number', 'LIKE', '%,%')
    ->update(['dsr_number' => Illuminate\Support\Facades\DB::raw("REPLACE(dsr_number, ',', '')")]);

echo "Fixed {$updated} dsr_number values\n";
