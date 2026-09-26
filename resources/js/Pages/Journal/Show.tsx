import { Link } from '@inertiajs/react';
import AppShell from '@/components/Shell';
import { Money } from '@/components/ui';

export default function Show({ entry }: { entry: { id: number; date: string; description: string; unit: string; number: string; transaction_id: number; poster: string; debit: number; credit: number; lines: { account: string; memo: string; debit: number; credit: number }[] } }) {
    return (
        <AppShell title={`Jurnal ${entry.number}`}>
            <div className="page-kicker">{entry.date} · {entry.unit} · {entry.poster}</div>
            <h1 className="page-title">{entry.description}</h1>
            <p><Link href={`/transactions/${entry.transaction_id}`}>{entry.number}</Link></p>
            <table className="data">
                <thead><tr><th>Akun</th><th className="right">Debit</th><th className="right">Kredit</th></tr></thead>
                <tbody>
                    {entry.lines.map((line) => (
                        <tr key={line.account + line.debit + line.credit}><td>{line.account}<div className="help">{line.memo}</div></td><td className="right"><Money value={line.debit} /></td><td className="right"><Money value={line.credit} /></td></tr>
                    ))}
                </tbody>
                <tfoot><tr><td>Jumlah</td><td className="right"><Money value={entry.debit} /></td><td className="right"><Money value={entry.credit} /></td></tr></tfoot>
            </table>
        </AppShell>
    );
}
