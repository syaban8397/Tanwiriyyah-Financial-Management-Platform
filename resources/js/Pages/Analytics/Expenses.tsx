import { Link, router } from '@inertiajs/react';
import AppShell from '@/components/Shell';
import { Money, PageHeader } from '@/components/ui';

export default function Expenses({ period, periods, level, rows }: { period: { id: number; name: string }; periods: { id: number; name: string }[]; level: string; rows: { id: number; label: string; amount: number; href: string }[] }) {
    const title = level === 'units' ? 'Beban per unit' : level === 'accounts' ? 'Beban per akun' : 'Transaksi';

    return (
        <AppShell title="Performa beban">
            <PageHeader kicker={period.name} title={title} lede="Dari total, ke unit, ke akun, lalu ke transaksi." action={
                <select aria-label="Periode" defaultValue={period.id} onChange={(event) => router.get('/analytics/expenses', { period_id: event.target.value })}>
                    {periods.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                </select>
            } />
            <table className="data">
                <thead><tr><th>Rincian</th><th className="right">Jumlah</th></tr></thead>
                <tbody>
                    {rows.length === 0 && <tr><td colSpan={2} className="help">Tidak ada beban terposting.</td></tr>}
                    {rows.map((row) => <tr key={row.href}><td><Link href={row.href}>{row.label}</Link></td><td className="right"><Money value={row.amount} /></td></tr>)}
                </tbody>
            </table>
        </AppShell>
    );
}
