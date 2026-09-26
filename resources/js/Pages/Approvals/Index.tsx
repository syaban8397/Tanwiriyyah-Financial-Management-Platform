import { Link } from '@inertiajs/react';
import AppShell from '@/components/Shell';
import { Money, PageHeader, Pager, Status } from '@/components/ui';
import type { Paginator, TransactionRow } from '@/types';

export default function Index({ transactions }: { transactions: Paginator<TransactionRow> }) {
    return (
        <AppShell title="Persetujuan">
            <PageHeader kicker="Pengendalian" title="Antrean keputusan" lede="Yang membuat transaksi tidak dapat menyetujui transaksinya sendiri." />
            {transactions.data.length === 0 ? <p className="help">Antrean kosong.</p> : (
                <table className="data">
                    <thead><tr><th>Nomor</th><th>Unit</th><th>Uraian</th><th>Status</th><th className="right">Jumlah</th></tr></thead>
                    <tbody>{transactions.data.map((row) => <tr key={row.id}><td><Link href={`/transactions/${row.id}`}>{row.number}</Link></td><td>{row.unit}</td><td>{row.description}<div className="help">{row.creator}</div></td><td><Status value={row.status} label={row.status_label} /></td><td className="right"><Money value={row.amount} /></td></tr>)}</tbody>
                </table>
            )}
            <Pager paginator={transactions} />
        </AppShell>
    );
}
