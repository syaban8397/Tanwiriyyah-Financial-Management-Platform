import { router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import AppShell from '@/components/Shell';
import { Money, Status } from '@/components/ui';

export default function Show({ statement, snapshot, candidates, can_match }: { statement: { id: number; date: string; status: string; bank: string; opening_balance: number; closing_balance: number; lines: { id: number; date: string; description: string; amount: number; reference: string | null; match_status: string; transaction: { id: number; number: string } | null }[] }; snapshot: { book_balance: number; statement_difference: number; book_difference: number; unmatched: number }; candidates: { id: number; number: string; amount: number; description: string }[]; can_match: boolean }) {
    return (
        <AppShell title="Rekening koran">
            <div className="page-kicker">{statement.date} · {statement.bank}</div>
            <h1 className="page-title">Pencocokan</h1>
            <div className="figures statement" style={{ margin: '12px 0' }}>
                <div className="figure"><div className="label">Saldo buku</div><div className="value" style={{ fontSize: 18 }}><Money value={snapshot.book_balance} /></div></div>
                <div className="figure"><div className="label">Selisih ke saldo koran</div><div className="value" style={{ fontSize: 18 }}><Money value={snapshot.book_difference} /></div></div>
                <div className="figure"><div className="label">Baris belum cocok</div><div className="value" style={{ fontSize: 18 }}>{snapshot.unmatched}</div></div>
            </div>
            <table className="data">
                <thead><tr><th>Tanggal</th><th>Uraian</th><th className="right">Jumlah</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    {statement.lines.map((line) => (
                        <tr key={line.id}>
                            <td>{line.date}</td>
                            <td>{line.description}<div className="help">{line.reference}</div></td>
                            <td className="right"><Money value={line.amount} /></td>
                            <td><Status value={line.match_status === 'matched' ? 'reconciled' : line.match_status === 'resolved' ? 'approved' : 'under_review'} label={line.match_status} /></td>
                            <td>{line.transaction ? <a href={`/transactions/${line.transaction.id}`}>{line.transaction.number}</a> : can_match && line.match_status === 'unmatched' && <Match lineId={line.id} candidates={candidates} />}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </AppShell>
    );
}

function Match({ lineId, candidates }: { lineId: number; candidates: { id: number; number: string; amount: number; description: string }[] }) {
    const form = useForm({ transaction_id: candidates[0]?.id ?? '', comment: '' });
    const match = (event: FormEvent) => { event.preventDefault(); form.post(`/reconciliation/lines/${lineId}/match`); };
    const resolve = (event: FormEvent) => { event.preventDefault(); router.post(`/reconciliation/lines/${lineId}/resolve`, { comment: form.data.comment || 'Diselesaikan manual' }); };

    return (
        <form onSubmit={match} style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
            <select value={form.data.transaction_id} onChange={(e) => form.setData('transaction_id', Number(e.target.value))} aria-label="Transaksi">
                {candidates.map((item) => <option key={item.id} value={item.id}>{item.number}</option>)}
            </select>
            <button className="btn" type="submit">Cocokkan</button>
            <button className="btn" type="button" onClick={resolve}>Selesaikan</button>
        </form>
    );
}
