import { Link } from '@inertiajs/react';
import AppShell from '@/components/Shell';
import { Money, PageHeader, Pager } from '@/components/ui';
import type { Paginator } from '@/types';

export default function Index({ entries, filters }: { entries: Paginator<{ id: number; date: string; number: string; transaction_id: number; unit: string; description: string; debit: number; credit: number }>; filters: { q?: string } }) {
    return (
        <AppShell title="Jurnal">
            <PageHeader kicker="Keuangan" title="Jurnal" lede="Setiap posting menghasilkan sepasang debit dan kredit yang sama." />
            <div className="panel">
                <table className="data">
                    <thead><tr><th>Tanggal</th><th>Transaksi</th><th>Unit</th><th>Uraian</th><th className="right">Debit</th><th className="right">Kredit</th></tr></thead>
                    <tbody>
                        {entries.data.map((entry) => (
                            <tr key={entry.id}>
                                <td>{entry.date}</td>
                                <td><Link href={`/journal/${entry.id}`}>{entry.number}</Link></td>
                                <td>{entry.unit}</td>
                                <td>{entry.description}</td>
                                <td className="right"><Money value={entry.debit} /></td>
                                <td className="right"><Money value={entry.credit} /></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <div style={{ padding: 10 }}><Pager paginator={entries} /></div>
            </div>
        </AppShell>
    );
}
