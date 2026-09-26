import { router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '@/components/Shell';
import { Money, Status } from '@/components/ui';
import { idempotencyKey } from '@/lib/money';

interface Detail {
    id: number;
    number: string;
    type_label: string;
    status: string;
    status_label: string;
    amount: number;
    effect?: string | null;
    description: string;
    reference?: string | null;
    transacted_on: string;
    posting_date: string;
    unit: { code: string; name: string };
    period: { name: string; status: string };
    category?: { code: string; name: string } | null;
    payment_label: string;
    cash_account?: { name: string } | null;
    bank_account?: { name: string; masked: string } | null;
    destination_cash?: string | null;
    destination_bank?: string | null;
    people: Record<string, string | null>;
    rejection_reason?: string | null;
    original?: { id: number; number: string } | null;
    adjustments: { id: number; number: string; amount: number; status_label: string; effect: string }[];
    timeline: { action: string; label: string; actor: string | null; comment: string | null; at: string | null }[];
    journal?: { id: number; date: string; balanced: boolean; lines: { account: string; debit: number; credit: number }[] } | null;
    documents: { id: number; name: string; kind_label: string; size: number }[];
    next?: { action: string; label: string } | null;
    can_edit: boolean;
    can_submit: boolean;
    can_reject: boolean;
    can_revise: boolean;
    can_adjust: boolean;
}

export default function Show({ transaction }: { transaction: Detail }) {
    const [rejecting, setRejecting] = useState(false);
    const [adjusting, setAdjusting] = useState(false);
    const reject = useForm({ reason: '' });
    const adjust = useForm({
        idempotency_key: idempotencyKey(),
        amount: transaction.amount,
        transacted_on: transaction.transacted_on,
        posting_date: transaction.posting_date,
        description: `Penyesuaian ${transaction.number}`,
    });

    const act = (action: string) => router.post(`/transactions/${transaction.id}/${action}`);
    const sendReject = (event: FormEvent) => {
        event.preventDefault();
        reject.post(`/transactions/${transaction.id}/reject`);
    };
    const sendAdjust = (event: FormEvent) => {
        event.preventDefault();
        adjust.post(`/transactions/${transaction.id}/adjust`);
    };

    return (
        <AppShell title={transaction.number}>
            <div className="statement">
                <div className="statement-head">
                    <div>
                        <div className="page-kicker">{transaction.number} · {transaction.type_label}</div>
                        <h1 className="page-title">{transaction.description}</h1>
                        <div style={{ marginTop: 8 }}><Status value={transaction.status} label={transaction.status_label} /></div>
                    </div>
                    <div className="num" style={{ fontSize: 28 }}><Money value={transaction.amount} /></div>
                </div>
                <div className="figures">
                    <div className="figure"><div className="label">Unit</div><div>{transaction.unit.code} · {transaction.unit.name}</div></div>
                    <div className="figure"><div className="label">Pembayaran</div><div>{transaction.payment_label} · {transaction.cash_account?.name || transaction.bank_account?.masked || '—'}</div></div>
                    <div className="figure"><div className="label">Akun</div><div>{transaction.category ? `${transaction.category.code} ${transaction.category.name}` : transaction.destination_bank || transaction.destination_cash || 'Transfer'}</div></div>
                </div>
            </div>
            {transaction.rejection_reason && <p className="panel panel-b" style={{ marginTop: 12 }}>Ditolak: {transaction.rejection_reason}</p>}
            <div style={{ display: 'flex', gap: 8, margin: '14px 0', flexWrap: 'wrap' }}>
                {transaction.can_edit && <a className="btn" href={`/transactions/${transaction.id}/edit`}>Ubah draf</a>}
                {transaction.can_submit && <button className="btn btn-primary" type="button" onClick={() => act('submit')}>Ajukan</button>}
                {transaction.can_revise && <button className="btn" type="button" onClick={() => act('revise')}>Jadikan draf</button>}
                {transaction.next && <button className="btn btn-primary" type="button" onClick={() => act(transaction.next!.action)}>{transaction.next.label}</button>}
                {transaction.can_reject && <button className="btn btn-danger" type="button" onClick={() => setRejecting(true)}>Tolak</button>}
                {transaction.can_adjust && <button className="btn" type="button" onClick={() => setAdjusting(true)}>Penyesuaian</button>}
            </div>
            {rejecting && (
                <form onSubmit={sendReject} className="panel panel-b" style={{ marginBottom: 12 }}>
                    <FieldLike label="Alasan penolakan" />
                    <textarea value={reject.data.reason} onChange={(event) => reject.setData('reason', event.target.value)} required minLength={3} style={{ width: '100%', minHeight: 80 }} />
                    {reject.errors.reason && <em className="error">{reject.errors.reason}</em>}
                    <button className="btn btn-danger" style={{ marginTop: 8 }} disabled={reject.processing}>Kirim penolakan</button>
                </form>
            )}
            {adjusting && (
                <form onSubmit={sendAdjust} className="panel panel-b grid-form" style={{ marginBottom: 12 }}>
                    <label className="field"><span>Nilai yang benar</span><input value={adjust.data.amount} onChange={(event) => adjust.setData('amount', Number(event.target.value.replace(/[^\d]/g, '') || 0))} /></label>
                    <label className="field"><span>Alasan</span><input value={adjust.data.description} onChange={(event) => adjust.setData('description', event.target.value)} /></label>
                    <button className="btn btn-primary" disabled={adjust.processing}>Buat draf penyesuaian</button>
                </form>
            )}
            <div className="split">
                <section className="panel">
                    <div className="panel-h"><h2>Dampak jurnal</h2>{transaction.journal && <span className="help">{transaction.journal.balanced ? 'Seimbang' : 'Tidak seimbang'}</span>}</div>
                    {transaction.journal ? (
                        <table className="data">
                            <thead><tr><th>Akun</th><th className="right">Debit</th><th className="right">Kredit</th></tr></thead>
                            <tbody>
                                {transaction.journal.lines.map((line) => (
                                    <tr key={line.account}><td>{line.account}</td><td className="right">{line.debit ? <Money value={line.debit} /> : '—'}</td><td className="right">{line.credit ? <Money value={line.credit} /> : '—'}</td></tr>
                                ))}
                            </tbody>
                        </table>
                    ) : <p className="panel-b help">Jurnal terbit setelah posting.</p>}
                    <div className="panel-h"><h2>Dokumen</h2></div>
                    <div className="panel-b">
                        {transaction.documents.length === 0 && <p className="help">Belum ada bukti.</p>}
                        {transaction.documents.map((document) => (
                            <div key={document.id}><a href={`/documents/${document.id}`}>{document.name}</a> <span className="help">{document.kind_label}</span></div>
                        ))}
                    </div>
                </section>
                <section className="panel">
                    <div className="panel-h"><h2>Linimasa</h2></div>
                    <div className="panel-b">
                        <ul className="timeline">
                            <li><div className="rail" /><div><strong>Dibuat</strong><div className="meta">{transaction.people.creator} · {transaction.transacted_on}</div></div></li>
                            {transaction.timeline.map((item) => (
                                <li key={item.action + item.at}><div className="rail" /><div><strong>{item.label}</strong><div className="meta">{item.actor} · {item.at?.replace('T', ' ').slice(0, 16)}</div>{item.comment && <div>{item.comment}</div>}</div></li>
                            ))}
                        </ul>
                        {transaction.original && <p className="help">Menyesuaikan <a href={`/transactions/${transaction.original.id}`}>{transaction.original.number}</a></p>}
                        {transaction.adjustments.map((item) => (
                            <p key={item.id}><a href={`/transactions/${item.id}`}>{item.number}</a> · {item.effect} · {item.status_label} · <Money value={item.amount} /></p>
                        ))}
                    </div>
                </section>
            </div>
        </AppShell>
    );
}

function FieldLike({ label }: { label: string }) {
    return <div className="help" style={{ marginBottom: 6 }}>{label}</div>;
}
