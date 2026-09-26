import { useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';
import AppShell from '@/components/Shell';
import { Money, PageHeader } from '@/components/ui';
import type { SharedProps } from '@/types';

export default function Index({ accounts }: { accounts: { id: number; bank_name: string; masked: string; number: string; unit: string; opening_balance: number; balance: number }[] }) {
    const { unit_options, auth } = usePage<SharedProps>().props;
    const form = useForm({ unit_id: auth?.user.unit_id ?? unit_options[0]?.id ?? '', bank_name: '', account_name: 'Rekening Operasional', account_number: '', opening_balance: 0 });
    const submit = (event: FormEvent) => { event.preventDefault(); form.post('/bank'); };

    return (
        <AppShell title="Bank">
            <PageHeader kicker="Kas & bank" title="Rekening bank" lede="Nomor rekening disimpan terenkripsi. Daftar menampilkan empat digit terakhir." />
            <table className="data">
                <thead><tr><th>Unit</th><th>Bank</th><th>Nomor</th><th className="right">Awal</th><th className="right">Saldo</th></tr></thead>
                <tbody>
                    {accounts.map((account) => (
                        <tr key={account.id}><td>{account.unit}</td><td>{account.bank_name}</td><td className="num">{account.masked}</td><td className="right"><Money value={account.opening_balance} /></td><td className="right"><Money value={account.balance} /></td></tr>
                    ))}
                </tbody>
            </table>
            <form onSubmit={submit} className="grid-form" style={{ marginTop: 16 }}>
                {!auth?.user.unit_id && <label className="field"><span>Unit</span><select value={form.data.unit_id} onChange={(e) => form.setData('unit_id', Number(e.target.value))}>{unit_options.map((unit) => <option key={unit.id} value={unit.id}>{unit.code}</option>)}</select></label>}
                <label className="field"><span>Bank</span><input value={form.data.bank_name} onChange={(e) => form.setData('bank_name', e.target.value)} /></label>
                <label className="field"><span>Nomor</span><input value={form.data.account_number} onChange={(e) => form.setData('account_number', e.target.value)} /></label>
                <label className="field"><span>Saldo awal</span><input value={form.data.opening_balance} onChange={(e) => form.setData('opening_balance', Number(e.target.value.replace(/\D/g, '') || 0))} /></label>
                <button className="btn">Tambah rekening</button>
            </form>
        </AppShell>
    );
}
