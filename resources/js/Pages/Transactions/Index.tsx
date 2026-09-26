import { Link, router } from '@inertiajs/react';
import AppShell from '@/components/Shell';
import { Empty, Money, PageHeader, Pager, Status } from '@/components/ui';
import type { Paginator, TransactionRow } from '@/types';

const statuses = [
    ['draft', 'Draf'],
    ['submitted', 'Diajukan'],
    ['under_review', 'Ditinjau'],
    ['approved', 'Disetujui'],
    ['posted', 'Diposting'],
    ['reconciled', 'Direkonsiliasi'],
    ['rejected', 'Ditolak'],
];

export default function Index({ transactions, filters, can_create }: { transactions: Paginator<TransactionRow>; filters: Record<string, string | undefined>; can_create: boolean }) {
    const setFilter = (key: string, value: string) => {
        router.get('/transactions', { ...filters, [key]: value || undefined }, { preserveState: true, replace: true });
    };
    const create = can_create ? <Link className="btn btn-primary" href="/transactions/create">Buat transaksi</Link> : null;

    return (
        <AppShell title="Transaksi">
            <PageHeader kicker="Keuangan" title="Transaksi" lede="Draf, pengajuan, dan jurnal yang sudah diposting. Nominal rata kanan." action={create} />
            <form className="filters" onSubmit={(event) => { event.preventDefault(); const data = new FormData(event.currentTarget); setFilter('q', String(data.get('q') ?? '')); }}>
                <input name="q" placeholder="Cari nomor atau uraian" aria-label="Cari transaksi" defaultValue={filters.q ?? ''} />
                <select defaultValue={filters.status ?? ''} onChange={(event) => setFilter('status', event.target.value)} aria-label="Status">
                    <option value="">Semua status</option>
                    {statuses.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                </select>
                <select defaultValue={filters.type ?? ''} onChange={(event) => setFilter('type', event.target.value)} aria-label="Jenis">
                    <option value="">Semua jenis</option>
                    <option value="income">Penerimaan</option>
                    <option value="expense">Pengeluaran</option>
                    <option value="transfer">Transfer</option>
                    <option value="adjustment">Penyesuaian</option>
                </select>
                <button className="btn" type="submit">Cari</button>
                <a className="btn" href={`/transactions/export?status=${filters.status ?? ''}&type=${filters.type ?? ''}`}>Ekspor</a>
            </form>
            {transactions.data.length === 0 ? (
                <Empty title="Belum ada transaksi." body="Transaksi yang diajukan unit ini akan muncul di sini." action={create} />
            ) : (
                <div className="panel">
                    <table className="data">
                        <thead>
                            <tr>
                                <th>Nomor</th><th>Tanggal</th><th className="hide-sm">Unit</th><th>Uraian</th><th>Status</th><th className="right">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            {transactions.data.map((row) => (
                                <tr key={row.id}>
                                    <td><Link href={`/transactions/${row.id}`}>{row.number}</Link><div className="help">{row.type_label}</div></td>
                                    <td>{row.transacted_on}</td>
                                    <td className="hide-sm">{row.unit}</td>
                                    <td>{row.description}<div className="help">{row.category}</div></td>
                                    <td><Status value={row.status} label={row.status_label} /></td>
                                    <td className="right"><Money value={row.amount} /></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <div style={{ padding: '0 10px 10px' }}><Pager paginator={transactions} /></div>
                </div>
            )}
        </AppShell>
    );
}
