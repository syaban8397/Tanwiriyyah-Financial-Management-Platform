import { useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '@/components/Shell';
import { Field, PageHeader } from '@/components/ui';
import { idempotencyKey } from '@/lib/money';

interface Catalog {
    units: { id: number; code: string; name: string }[];
    cash_accounts: { id: number; name: string; unit_id: number }[];
    bank_accounts: { id: number; name: string; masked: string; unit_id: number }[];
}

export default function Create({ catalog }: { catalog: Catalog }) {
    const [unitId, setUnitId] = useState(catalog.units[0]?.id ?? 0);
    const form = useForm({
        idempotency_key: idempotencyKey(),
        unit_id: unitId,
        transacted_on: new Date().toISOString().slice(0, 10),
        posting_date: new Date().toISOString().slice(0, 10),
        amount: 0,
        description: 'Setoran kas ke bank',
        reference: '',
        source_type: 'cash',
        source_id: '' as number | '',
        destination_type: 'bank',
        destination_id: '' as number | '',
    });
    const cash = catalog.cash_accounts.filter((account) => account.unit_id === Number(form.data.unit_id));
    const banks = catalog.bank_accounts.filter((account) => account.unit_id === Number(form.data.unit_id));
    const submit = (event: FormEvent) => { event.preventDefault(); form.post('/transfers'); };

    return (
        <AppShell title="Transfer">
            <PageHeader kicker="Kas & bank" title="Transfer" lede="Memindahkan dana. Neto yayasan tidak berubah karena bukan pendapatan atau beban." />
            <form onSubmit={submit} className="grid-form panel panel-b">
                <Field label="Unit"><select value={form.data.unit_id} onChange={(e) => { setUnitId(Number(e.target.value)); form.setData('unit_id', Number(e.target.value)); }}>{catalog.units.map((unit) => <option key={unit.id} value={unit.id}>{unit.code}</option>)}</select></Field>
                <Field label="Jumlah" error={form.errors.amount}><input value={form.data.amount || ''} onChange={(e) => form.setData('amount', Number(e.target.value.replace(/\D/g, '') || 0))} /></Field>
                <Field label="Dari"><select value={form.data.source_id} onChange={(e) => form.setData('source_id', Number(e.target.value))}>{cash.map((account) => <option key={account.id} value={account.id}>{account.name}</option>)}</select></Field>
                <Field label="Ke"><select value={form.data.destination_id} onChange={(e) => form.setData('destination_id', Number(e.target.value))}>{banks.map((account) => <option key={account.id} value={account.id}>{account.name} {account.masked}</option>)}</select></Field>
                <Field label="Tanggal"><input type="date" value={form.data.transacted_on} onChange={(e) => { form.setData('transacted_on', e.target.value); form.setData('posting_date', e.target.value); }} /></Field>
                <Field label="Uraian" error={form.errors.description}><input value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} /></Field>
                <button className="btn btn-primary" disabled={form.processing}>Simpan draf transfer</button>
            </form>
        </AppShell>
    );
}
