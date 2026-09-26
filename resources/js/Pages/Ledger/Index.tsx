import { router } from '@inertiajs/react';
import AppShell from '@/components/Shell';
import { Money, PageHeader } from '@/components/ui';

export default function Index({ accounts, periods, filters, ledger }: { accounts: { id: number; code: string; name: string }[]; periods: { id: number; name: string }[]; filters: { account_id?: number; period_id?: number }; ledger: { opening: number; debit: number; credit: number; closing: number; rows: { id: number; date: string; description: string; unit: string | null; debit: number; credit: number; balance: number; transaction_id: number }[] } }) {
    return (
        <AppShell title="Buku besar">
            <PageHeader kicker="Keuangan" title="Buku besar" lede="Saldo awal, mutasi, dan saldo berjalan." />
            <div className="filters">
                <select aria-label="Akun" value={filters.account_id} onChange={(event) => router.get('/ledger', { ...filters, account_id: event.target.value }, { preserveState: true })}>
                    {accounts.map((account) => <option key={account.id} value={account.id}>{account.code} {account.name}</option>)}
                </select>
                <select aria-label="Periode" value={filters.period_id} onChange={(event) => router.get('/ledger', { ...filters, period_id: event.target.value }, { preserveState: true })}>
                    {periods.map((period) => <option key={period.id} value={period.id}>{period.name}</option>)}
                </select>
            </div>
            <div className="figures statement">
                <div className="figure"><div className="label">Saldo awal</div><div className="value"><Money value={ledger.opening} /></div></div>
                <div className="figure"><div className="label">Mutasi</div><div className="value" style={{ fontSize: 16 }}><Money value={ledger.debit} /> / <Money value={ledger.credit} /></div></div>
                <div className="figure"><div className="label">Saldo akhir</div><div className="value"><Money value={ledger.closing} /></div></div>
            </div>
            <table className="data" style={{ marginTop: 12 }}>
                <thead><tr><th>Tanggal</th><th>Uraian</th><th>Unit</th><th className="right">Debit</th><th className="right">Kredit</th><th className="right">Saldo</th></tr></thead>
                <tbody>
                    {ledger.rows.length === 0 && <tr><td colSpan={6} className="help">Tidak ada mutasi pada periode ini.</td></tr>}
                    {ledger.rows.map((row) => (
                        <tr key={row.id}><td>{row.date}</td><td><a href={`/transactions/${row.transaction_id}`}>{row.description}</a></td><td>{row.unit}</td><td className="right"><Money value={row.debit} /></td><td className="right"><Money value={row.credit} /></td><td className="right"><Money value={row.balance} /></td></tr>
                    ))}
                </tbody>
            </table>
        </AppShell>
    );
}
