import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import AppShell from '@/components/Shell';
import { PageHeader } from '@/components/ui';

export default function Units({ units }: { units: { id: number; code: string; name: string; short_name: string; is_active: boolean; users_count: number }[] }) {
    const form = useForm({ code: '', name: '', short_name: '', cash_opening: 0, bank_opening: 0, bank_name: 'BSI', account_number: '' });
    const submit = (event: FormEvent) => { event.preventDefault(); form.post('/admin/units'); };

    return (
        <AppShell title="Unit">
            <PageHeader kicker="Administrasi" title="Unit organisasi" lede="Unit baru langsung mendapat kas dan rekening. Tidak perlu mengubah kode program." />
            <table className="data"><thead><tr><th>Kode</th><th>Nama</th><th>Pengguna</th><th>Aktif</th></tr></thead><tbody>{units.map((unit) => <tr key={unit.id}><td>{unit.code}</td><td>{unit.name}</td><td>{unit.users_count}</td><td>{unit.is_active ? 'Ya' : 'Tidak'}</td></tr>)}</tbody></table>
            <form onSubmit={submit} className="grid-form" style={{ marginTop: 16 }}>
                <label className="field"><span>Kode</span><input value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} /></label>
                <label className="field"><span>Nama</span><input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></label>
                <label className="field"><span>Nama pendek</span><input value={form.data.short_name} onChange={(e) => form.setData('short_name', e.target.value)} /></label>
                <label className="field"><span>Saldo kas awal</span><input value={form.data.cash_opening} onChange={(e) => form.setData('cash_opening', Number(e.target.value.replace(/\D/g, '') || 0))} /></label>
                <label className="field"><span>Saldo bank awal</span><input value={form.data.bank_opening} onChange={(e) => form.setData('bank_opening', Number(e.target.value.replace(/\D/g, '') || 0))} /></label>
                <label className="field"><span>Nomor rekening</span><input value={form.data.account_number} onChange={(e) => form.setData('account_number', e.target.value)} /></label>
                <button className="btn btn-primary">Tambah unit</button>
            </form>
        </AppShell>
    );
}
