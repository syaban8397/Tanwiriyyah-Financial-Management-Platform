import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import AppShell from '@/components/Shell';
import { PageHeader } from '@/components/ui';

export default function Index({ accounts, parents }: { accounts: { id: number; code: string; name: string; type_label: string; parent: string | null; is_postable: boolean; is_active: boolean }[]; parents: { id: number; code: string; name: string }[] }) {
    const form = useForm({ code: '', name: '', type: 'expense', parent_id: parents[0]?.id ?? '' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/accounts', { preserveScroll: true, onSuccess: () => form.reset('code', 'name') });
    };

    return (
        <AppShell title="Bagan akun">
            <PageHeader kicker="Keuangan" title="Bagan akun" lede="Akun induk mengelompokkan. Hanya akun yang dapat diposting yang dipakai transaksi." />
            <table className="data">
                <thead><tr><th>Kode</th><th>Nama</th><th>Jenis</th><th>Induk</th><th>Posting</th></tr></thead>
                <tbody>
                    {accounts.map((account) => (
                        <tr key={account.id}><td className="num">{account.code}</td><td>{account.name}</td><td>{account.type_label}</td><td>{account.parent ?? '—'}</td><td>{account.is_postable ? 'Ya' : 'Kelompok'}</td></tr>
                    ))}
                </tbody>
            </table>
            <form onSubmit={submit} className="panel panel-b grid-form" style={{ marginTop: 16 }}>
                <label className="field"><span>Kode</span><input value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} /></label>
                <label className="field"><span>Nama</span><input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></label>
                <label className="field"><span>Jenis</span>
                    <select value={form.data.type} onChange={(event) => form.setData('type', event.target.value)}>
                        <option value="asset">Aset</option><option value="liability">Kewajiban</option><option value="equity">Dana</option><option value="income">Pendapatan</option><option value="expense">Beban</option>
                    </select>
                </label>
                <label className="field"><span>Induk</span>
                    <select value={form.data.parent_id} onChange={(event) => form.setData('parent_id', Number(event.target.value))}>
                        {parents.map((parent) => <option key={parent.id} value={parent.id}>{parent.code} {parent.name}</option>)}
                    </select>
                </label>
                <button className="btn btn-primary" disabled={form.processing}>Tambah akun</button>
            </form>
        </AppShell>
    );
}
