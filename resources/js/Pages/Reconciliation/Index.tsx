import { Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import AppShell from '@/components/Shell';
import { Money, PageHeader, Pager, Status } from '@/components/ui';
import type { Paginator } from '@/types';

export default function Index({ statements, accounts, can_import }: { statements: Paginator<{ id: number; date: string; bank: string; unit: string; status: string; closing_balance: number }>; accounts: { id: number; label: string }[]; can_import: boolean }) {
    const form = useForm<{ bank_account_id: string | number; statement_date: string; opening_balance: number; closing_balance: number; file: File | null }>({
        bank_account_id: accounts[0]?.id ?? '',
        statement_date: new Date().toISOString().slice(0, 10),
        opening_balance: 0,
        closing_balance: 0,
        file: null,
    });
    const submit = (event: FormEvent) => { event.preventDefault(); form.post('/reconciliation/import', { forceFormData: true }); };

    return (
        <AppShell title="Rekonsiliasi">
            <PageHeader kicker="Kas & bank" title="Rekonsiliasi" lede="Impor CSV dengan kolom date, description, amount, reference. Cocokkan ke transaksi yang sudah diposting." />
            {statements.data.length === 0 ? <p className="help">Belum ada rekening koran.</p> : (
                <table className="data">
                    <thead><tr><th>Tanggal</th><th>Unit</th><th>Bank</th><th>Status</th><th className="right">Saldo koran</th></tr></thead>
                    <tbody>{statements.data.map((row) => <tr key={row.id}><td><Link href={`/reconciliation/${row.id}`}>{row.date}</Link></td><td>{row.unit}</td><td>{row.bank}</td><td><Status value={row.status === 'reconciled' ? 'reconciled' : 'under_review'} label={row.status === 'reconciled' ? 'Selesai' : 'Terbuka'} /></td><td className="right"><Money value={row.closing_balance} /></td></tr>)}</tbody>
                </table>
            )}
            <Pager paginator={statements} />
            {can_import && (
                <form onSubmit={submit} className="grid-form" style={{ marginTop: 16 }}>
                    <label className="field"><span>Rekening</span><select value={form.data.bank_account_id} onChange={(e) => form.setData('bank_account_id', Number(e.target.value))}>{accounts.map((account) => <option key={account.id} value={account.id}>{account.label}</option>)}</select></label>
                    <label className="field"><span>Tanggal</span><input type="date" value={form.data.statement_date} onChange={(e) => form.setData('statement_date', e.target.value)} /></label>
                    <label className="field"><span>Saldo awal koran</span><input value={form.data.opening_balance} onChange={(e) => form.setData('opening_balance', Number(e.target.value.replace(/[^\d-]/g, '') || 0))} /></label>
                    <label className="field"><span>Saldo akhir koran</span><input value={form.data.closing_balance} onChange={(e) => form.setData('closing_balance', Number(e.target.value.replace(/[^\d-]/g, '') || 0))} /></label>
                    <label className="field"><span>CSV</span><input type="file" accept=".csv,text/csv" onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)} /></label>
                    <button className="btn btn-primary" disabled={form.processing}>Impor</button>
                </form>
            )}
        </AppShell>
    );
}
