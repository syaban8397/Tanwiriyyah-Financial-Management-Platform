import { useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';
import AppShell from '@/components/Shell';
import { Money, PageHeader } from '@/components/ui';
import type { SharedProps } from '@/types';

export default function Index({ accounts }: { accounts: { id: number; name: string; unit: string; opening_balance: number; movement: number; balance: number }[] }) {
    const { unit_options, auth } = usePage<SharedProps>().props;
    const form = useForm({ unit_id: auth?.user.unit_id ?? unit_options[0]?.id ?? '', name: '', opening_balance: 0 });
    const submit = (event: FormEvent) => { event.preventDefault(); form.post('/cash'); };

    return (
        <AppShell title="Kas">
            <PageHeader kicker="Kas & bank" title="Kas" lede="Saldo = saldo awal ditambah mutasi jurnal. Transfer mengurangi kas tanpa menjadi beban." />
            <table className="data">
                <thead><tr><th>Unit</th><th>Nama</th><th className="right">Awal</th><th className="right">Mutasi</th><th className="right">Saldo</th></tr></thead>
                <tbody>
                    {accounts.map((account) => (
                        <tr key={account.id}><td>{account.unit}</td><td>{account.name}</td><td className="right"><Money value={account.opening_balance} /></td><td className="right"><Money value={account.movement} /></td><td className="right"><Money value={account.balance} /></td></tr>
                    ))}
                </tbody>
            </table>
            <form onSubmit={submit} className="grid-form" style={{ marginTop: 16 }}>
                {!auth?.user.unit_id && <label className="field"><span>Unit</span><select value={form.data.unit_id} onChange={(e) => form.setData('unit_id', Number(e.target.value))}>{unit_options.map((unit) => <option key={unit.id} value={unit.id}>{unit.code}</option>)}</select></label>}
                <label className="field"><span>Nama</span><input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></label>
                <label className="field"><span>Saldo awal</span><input value={form.data.opening_balance} onChange={(e) => form.setData('opening_balance', Number(e.target.value.replace(/\D/g, '') || 0))} /></label>
                <button className="btn">Tambah kas</button>
            </form>
        </AppShell>
    );
}
