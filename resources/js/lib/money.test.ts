import { describe, expect, it } from 'vitest';
import { formatRupiah, parseRupiah } from './money';

describe('rupiah', () => {
    it('formats grouped rupiah without fractional units', () => {
        expect(formatRupiah(15000000)).toBe('Rp 15.000.000');
        expect(formatRupiah(-2500)).toBe('-Rp 2.500');
    });

    it('parses formatted input back to an integer', () => {
        expect(parseRupiah('Rp 15.000.000')).toBe(15000000);
        expect(parseRupiah('-3.200.000')).toBe(-3200000);
    });
});
