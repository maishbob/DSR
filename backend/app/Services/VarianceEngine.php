<?php

namespace App\Services;

use App\Models\DailySalesRecord;

/**
 * VarianceEngine
 *
 * Classifies fuel stock variances into OK / WARNING / CRITICAL based on
 * configurable thresholds (config/dsr.php).
 *
 * Deliberately stateless — all methods are pure functions of their inputs
 * so results are deterministic and testable without database access.
 */
class VarianceEngine
{
    /** @var array{warning_pct: float, critical_pct: float, warning_abs: float, critical_abs: float} */
    private array $thresholds;

    public function __construct()
    {
        $this->thresholds = config('dsr.variance_thresholds');
    }

    /**
     * Classify a single product line variance.
     *
     * @param  float $variance    Actual − Expected stock (negative = shortage)
     * @param  float $litresSold  Denominator for percentage calculation
     * @return array{status: string, variance_pct: float, abs_variance: float}
     */
    public function classifyLine(float $variance, float $litresSold): array
    {
        $abs = abs($variance);
        $pct = $litresSold > 0 ? round(($abs / $litresSold) * 100, 3) : 0.0;

        $status = $this->resolveStatus($abs, $pct);

        return [
            'status'       => $status,
            'variance_pct' => $pct,
            'abs_variance' => $abs,
        ];
    }

    /**
     * Determine the worst-case status across all DSR line items.
     * The overall DSR status = worst individual product status.
     */
    public function classifyDsr(DailySalesRecord $dsr): string
    {
        $worst = 'ok';

        foreach ($dsr->lineItems as $line) {
            $result = $this->classifyLine(
                (float) $line->variance,
                (float) $line->litres_sold,
            );

            $worst = $this->worstStatus($worst, $result['status']);

            if ($worst === 'critical') break; // Can't get worse
        }

        return $worst;
    }

    /**
     * Returns true if the DSR may be approved without an override reason.
     */
    public function canApproveWithoutOverride(string $status): bool
    {
        return in_array($status, ['ok', 'warning'], true);
    }

    /**
     * Human-readable summary for a given status.
     */
    public function statusLabel(string $status): string
    {
        return match ($status) {
            'ok'       => 'Within tolerance',
            'warning'  => 'Variance notable — review before approving',
            'critical' => 'Variance exceeds threshold — override required to approve',
            default    => 'Unknown',
        };
    }

    // -------------------------------------------------------------------------

    private function resolveStatus(float $abs, float $pct): string
    {
        $t = $this->thresholds;

        if ($abs >= $t['critical_abs'] || $pct >= $t['critical_pct']) {
            return 'critical';
        }

        if ($abs >= $t['warning_abs'] || $pct >= $t['warning_pct']) {
            return 'warning';
        }

        return 'ok';
    }

    private function worstStatus(string $a, string $b): string
    {
        $rank = ['ok' => 0, 'warning' => 1, 'critical' => 2];
        return ($rank[$a] ?? 0) >= ($rank[$b] ?? 0) ? $a : $b;
    }
}
