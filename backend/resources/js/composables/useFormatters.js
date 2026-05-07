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

export function fmtReading(n, dec = 3) {
    if (n === null || n === undefined) return '0'.padEnd(dec + 2, '0');
    return Number(n).toFixed(dec);
}

export function fmtDate(d) {
    if (!d) return '—';
    const dt = new Date(d);
    const dd = String(dt.getDate()).padStart(2, '0');
    const mm = String(dt.getMonth() + 1).padStart(2, '0');
    const yyyy = dt.getFullYear();
    return `${dd}/${mm}/${yyyy}`;
}
