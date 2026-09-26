import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import AppShell from '@/components/Shell';
import { PageHeader } from '@/components/ui';

export default function Form({ periods, units, accounts }: { periods: { id: number; name: string }[]; units: { id: number; code: string; name: string }[]; accounts: { id: number; code: string; name: string }[] }) {
    const form = useForm({
        unit_id: units[0]?.id ?? '',
        period_id: periods[0]?.id ?? '',
        name: 'Anggaran unit',
        lines: accounts.slice(0, 3).map((account) => ({ account_id: account.id, planned: 0 })),
    });
    const submit = (event: FormEvent) => { event.preventDefault(); form.post('/budgets'); };

    return (
        <AppShell title="Anggaran baru">
            <PageHeader title="Susun anggaran" lede="Isi rencana per akun beban. Persetujuan terpisah dari penyusunan." />
            <form onSubmit={submit} className="panel panel-b">
                <div className="grid-form">
                    <label className="field"><span>Unit</span><select value={form.data.unit_id} onChange={(e) => form.setData('unit_id', Number(e.target.value))}>{units.map((unit) => <option key={unit.id} value={unit.id}>{unit.code}</option>)}</select></label>
                    <label className="field"><span>Periode</span><select value={form.data.period_id} onChange={(e) => form.setData('period_id', Number(e.target.value))}>{periods.map((period) => <option key={period.id} value={period.id}>{period.name}</option>)}</select></label>
                    <label className="field"><span>Nama</span><input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></label>
                </div>
                {form.data.lines.map((line, index) => (
                    <div key={index} className="grid-form" style={{ marginTop: 8 }}>
                        <select value={line.account_id} onChange={(e) => { const lines = [...form.data.lines]; lines[index] = { ...line, account_id: Number(e.target.value) }; form.setData('lines', lines); }}>
                            {accounts.map((account) => <option key={account.id} value={account.id}>{account.code} {account.name}</option>)}
                        </select>
                        <input value={line.planned} onChange={(e) => { const lines = [...form.data.lines]; lines[index] = { ...line, planned: Number(e.target.value.replace(/\D/g, '') || 0) }; form.setData('lines', lines); }} />
                    </div>
                ))}
                <button className="btn btn-primary" style={{ marginTop: 12 }} disabled={form.processing}>Simpan</button>
            </form>
        </AppShell>
    );
}
