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
    const s = String(d).slice(0, 10); // "YYYY-MM-DD" from any ISO string
    const [yyyy, mm, dd] = s.split('-');
    if (!yyyy || !mm || !dd) return '—';
    return `${dd}/${mm}/${yyyy}`;
}
