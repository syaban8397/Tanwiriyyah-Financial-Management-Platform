import { router, useForm } from '@inertiajs/react';
import { FormEvent, useEffect } from 'react';
import AppShell from '@/components/Shell';
import { Money, PageHeader } from '@/components/ui';

interface Run { id: number; report: string; status: string; format: string; error?: string | null }

export default function Index({ catalog, periods, units, accounts, filters, preview, runs, can_export }: { catalog: { key: string; group: string; title: string; description: string }[]; periods: { id: number; name: string }[]; units: { id: number; code: string }[]; accounts: { id: number; code: string; name: string }[]; filters: Record<string, string | undefined>; preview: { title: string; columns: string[]; rows: (string | number)[][]; total: number; total_label: string } | null; runs: Run[]; can_export: boolean }) {
    const form = useForm({ report: filters.report ?? 'income', format: 'xlsx', period_id: filters.period_id ?? periods[0]?.id ?? '', unit_id: filters.unit_id ?? '', account_id: filters.account_id ?? '' });
    const pending = runs.some((run) => run.status === 'preparing' || run.status === 'generating');

    useEffect(() => {
        if (!pending) return;
        const timer = window.setInterval(() => router.reload({ only: ['runs'] }), 2500);
        return () => window.clearInterval(timer);
    }, [pending]);

    const previewNow = () => router.get('/reports', { report: form.data.report, period_id: form.data.period_id, unit_id: form.data.unit_id, account_id: form.data.account_id }, { preserveState: true });
    const generate = (event: FormEvent) => { event.preventDefault(); form.post('/reports'); };
    const groups = [...new Set(catalog.map((item) => item.group))];

    return (
        <AppShell title="Laporan">
            <PageHeader kicker="Pengendalian" title="Pusat laporan" lede="Pratinjau memakai agregat buku besar. Berkas Excel dan PDF disiapkan di latar belakang." />
            <div className="split">
                <div>
                    {groups.map((group) => (
                        <section key={group} style={{ marginBottom: 14 }}>
                            <div className="page-kicker">{group}</div>
                            {catalog.filter((item) => item.group === group).map((item) => (
                                <button key={item.key} className="btn" style={{ display: 'block', width: '100%', textAlign: 'left', marginTop: 6, background: form.data.report === item.key ? '#efe6d6' : undefined }} type="button" onClick={() => form.setData('report', item.key)}>
                                    <strong>{item.title}</strong>
                                    <div className="help">{item.description}</div>
                                </button>
                            ))}
                        </section>
                    ))}
                </div>
                <form onSubmit={generate} className="panel panel-b">
                    <label className="field"><span>Periode</span><select value={form.data.period_id} onChange={(e) => form.setData('period_id', e.target.value)}>{periods.map((period) => <option key={period.id} value={period.id}>{period.name}</option>)}</select></label>
                    <label className="field"><span>Unit</span><select value={form.data.unit_id} onChange={(e) => form.setData('unit_id', e.target.value)}><option value="">Semua yang boleh dilihat</option>{units.map((unit) => <option key={unit.id} value={unit.id}>{unit.code}</option>)}</select></label>
                    {form.data.report === 'ledger' && <label className="field"><span>Akun</span><select value={form.data.account_id} onChange={(e) => form.setData('account_id', e.target.value)}>{accounts.map((account) => <option key={account.id} value={account.id}>{account.code} {account.name}</option>)}</select></label>}
                    <div style={{ display: 'flex', gap: 8, marginTop: 10 }}>
                        <button className="btn" type="button" onClick={previewNow}>Tampilkan</button>
                        {can_export && <button className="btn btn-primary" disabled={form.processing}>Siapkan Excel</button>}
                        {can_export && <button className="btn" type="button" onClick={() => { form.transform((data) => ({ ...data, format: 'pdf' })); form.post('/reports', { onFinish: () => form.transform((data) => data) }); }}>Siapkan PDF</button>}
                    </div>
                    <div style={{ marginTop: 16 }}>
                        {runs.map((run) => (
                            <div key={run.id} className="compare">
                                <span>{run.report}</span>
                                <span>{run.status === 'ready' ? 'Siap' : run.status === 'failed' ? 'Gagal' : 'Disiapkan'}</span>
                                {run.status === 'ready' ? <a href={`/reports/runs/${run.id}/download`}>Unduh</a> : <span className="help">{run.error}</span>}
                            </div>
                        ))}
                    </div>
                </form>
            </div>
            {preview && (
                <section className="panel" style={{ marginTop: 16 }}>
                    <div className="panel-h"><h2>{preview.title}</h2><span className="num">{preview.total_label}: <Money value={preview.total} /></span></div>
                    <table className="data">
                        <thead><tr>{preview.columns.map((column) => <th key={column}>{column}</th>)}</tr></thead>
                        <tbody>{preview.rows.map((row, index) => <tr key={index}>{row.map((cell, cellIndex) => <td key={cellIndex} className={typeof cell === 'number' ? 'right num' : ''}>{typeof cell === 'number' ? cell.toLocaleString('id-ID') : cell}</td>)}</tr>)}</tbody>
                    </table>
                </section>
            )}
        </AppShell>
    );
}
