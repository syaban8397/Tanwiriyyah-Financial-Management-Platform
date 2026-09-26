export function formatRupiah(value: number | null | undefined): string {
    const amount = Number(value ?? 0);
    const sign = amount < 0 ? '-' : '';
    const formatted = Math.abs(Math.trunc(amount)).toLocaleString('id-ID');

    return `${sign}Rp ${formatted}`;
}

export function parseRupiah(input: string): number {
    const negative = input.trim().startsWith('-');
    const digits = input.replace(/[^\d]/g, '');
    const amount = digits === '' ? 0 : Number(digits);

    return negative ? -amount : amount;
}

export function idempotencyKey(): string {
    return crypto.randomUUID();
}
