/**
 * Shared formatting helpers used across all pages.
 *
 * Usage:
 *   import { fmt, fmt2, fmtDate } from '@/composables/useFormatters';
 */

export function fmt(n, dec = 2) {
    return Number(n ?? 0).toLocaleString('en-KE', {
        minimumFractionDigits: dec,
        maximumFractionDigits: dec,
    });
}

export function fmt2(n) {
    return fmt(n, 2);
}

export function fmtDate(d) {
    return d ? new Date(d).toLocaleDateString('en-KE') : '—';
}
