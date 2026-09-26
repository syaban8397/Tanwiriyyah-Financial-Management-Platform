import { useForm } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';
import AppShell from '@/components/Shell';
import { Field, Money, PageHeader } from '@/components/ui';
import { idempotencyKey } from '@/lib/money';

interface Catalog {
    units: { id: number; code: string; name: string }[];
    accounts: { id: number; code: string; name: string; type: string }[];
    cash_accounts: { id: number; name: string; unit_id: number }[];
    bank_accounts: { id: number; name: string; masked: string; unit_id: number }[];
}

interface Existing {
    id: number;
    type: string;
    unit: { id: number };
    amount: number;
    description: string;
    reference?: string | null;
    transacted_on: string;
    posting_date: string;
    payment_method: string;
    category?: { id: number } | null;
    cash_account?: { id: number } | null;
    bank_account?: { id: number } | null;
    lock_version: number;
}

const steps = ['Rincian', 'Keuangan', 'Pembayaran', 'Bukti', 'Tinjau'];

export default function Form({ mode, transaction, catalog }: { mode: 'create' | 'edit'; transaction: Existing | null; catalog: Catalog }) {
    const [step, setStep] = useState(0);
    const form = useForm({
        idempotency_key: idempotencyKey(),
        unit_id: transaction?.unit.id ?? catalog.units[0]?.id ?? '',
        type: transaction?.type ?? 'expense',
        transacted_on: transaction?.transacted_on ?? new Date().toISOString().slice(0, 10),
        posting_date: transaction?.posting_date ?? new Date().toISOString().slice(0, 10),
        category_account_id: transaction?.category?.id ?? '',
        payment_method: transaction?.payment_method ?? 'cash',
        cash_account_id: transaction?.cash_account?.id ?? '',
        bank_account_id: transaction?.bank_account?.id ?? '',
        amount: transaction?.amount ?? 0,
        description: transaction?.description ?? '',
        reference: transaction?.reference ?? '',
        document_kinds: ['invoice'] as string[],
        documents: [] as File[],
    });

    const accounts = useMemo(() => catalog.accounts.filter((account) => account.type === (form.data.type === 'income' ? 'income' : 'expense')), [catalog.accounts, form.data.type]);
    const cash = catalog.cash_accounts.filter((account) => String(account.unit_id) === String(form.data.unit_id));
    const banks = catalog.bank_accounts.filter((account) => String(account.unit_id) === String(form.data.unit_id));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { forceFormData: true };
        if (mode === 'edit' && transaction) {
            form.put(`/transactions/${transaction.id}`, options);
        } else {
            form.post('/transactions', options);
        }
    };

    return (
        <AppShell title={mode === 'edit' ? 'Ubah draf' : 'Transaksi baru'}>
            <PageHeader kicker="Transaksi" title={mode === 'edit' ? 'Ubah draf' : 'Catat transaksi'} lede="Isi bertahap. Pengajuan dilakukan setelah draf tersimpan." />
            <form onSubmit={submit} className="panel">
                <div className="steps">
                    {steps.map((label, index) => (
                        <button type="button" key={label} className={step === index ? 'on' : ''} onClick={() => setStep(index)}>{index + 1}. {label}</button>
                    ))}
                </div>
                <div className="panel-b grid-form">
                    {step === 0 && (
                        <>
                            <Field label="Unit" error={form.errors.unit_id}>
                                <select value={form.data.unit_id} onChange={(event) => form.setData('unit_id', Number(event.target.value))}>
                                    {catalog.units.map((unit) => <option key={unit.id} value={unit.id}>{unit.code} — {unit.name}</option>)}
                                </select>
                            </Field>
                            <Field label="Jenis" error={form.errors.type}>
                                <select value={form.data.type} onChange={(event) => form.setData('type', event.target.value)}>
                                    <option value="income">Penerimaan</option>
                                    <option value="expense">Pengeluaran</option>
                                </select>
                            </Field>
                            <Field label="Tanggal transaksi"><input type="date" value={form.data.transacted_on} onChange={(event) => form.setData('transacted_on', event.target.value)} /></Field>
                            <Field label="Tanggal posting" error={form.errors.posting_date}><input type="date" value={form.data.posting_date} onChange={(event) => form.setData('posting_date', event.target.value)} /></Field>
                            <Field label="Uraian" error={form.errors.description}><input value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} /></Field>
                            <Field label="Referensi"><input value={form.data.reference} onChange={(event) => form.setData('reference', event.target.value)} /></Field>
                        </>
                    )}
                    {step === 1 && (
                        <>
                            <Field label="Akun" error={form.errors.category_account_id}>
                                <select value={form.data.category_account_id} onChange={(event) => form.setData('category_account_id', Number(event.target.value))}>
                                    <option value="">Pilih akun</option>
                                    {accounts.map((account) => <option key={account.id} value={account.id}>{account.code} {account.name}</option>)}
                                </select>
                            </Field>
                            <Field label="Jumlah (rupiah)" error={form.errors.amount}>
                                <input inputMode="numeric" value={form.data.amount || ''} onChange={(event) => form.setData('amount', Number(event.target.value.replace(/[^\d]/g, '') || 0))} />
                            </Field>
                        </>
                    )}
                    {step === 2 && (
                        <>
                            <Field label="Dibayar lewat">
                                <select value={form.data.payment_method} onChange={(event) => form.setData('payment_method', event.target.value)}>
                                    <option value="cash">Kas</option>
                                    <option value="bank">Bank</option>
                                </select>
                            </Field>
                            {form.data.payment_method === 'cash' ? (
                                <Field label="Akun kas" error={form.errors.cash_account_id}>
                                    <select value={form.data.cash_account_id} onChange={(event) => form.setData('cash_account_id', Number(event.target.value))}>
                                        <option value="">Pilih kas</option>
                                        {cash.map((account) => <option key={account.id} value={account.id}>{account.name}</option>)}
                                    </select>
                                </Field>
                            ) : (
                                <Field label="Rekening" error={form.errors.bank_account_id}>
                                    <select value={form.data.bank_account_id} onChange={(event) => form.setData('bank_account_id', Number(event.target.value))}>
                                        <option value="">Pilih rekening</option>
                                        {banks.map((account) => <option key={account.id} value={account.id}>{account.name} {account.masked}</option>)}
                                    </select>
                                </Field>
                            )}
                        </>
                    )}
                    {step === 3 && (
                        <Field label="Berkas bukti (PDF atau gambar, maks. 5 MB)">
                            <input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" onChange={(event) => form.setData('documents', event.target.files ? Array.from(event.target.files) : [])} />
                        </Field>
                    )}
                    {step === 4 && (
                        <div>
                            <p>{form.data.description || 'Uraian belum diisi.'}</p>
                            <p className="num" style={{ fontSize: 22 }}><Money value={Number(form.data.amount)} /></p>
                            <p className="help">Simpan sebagai draf. Pengajuan ada di halaman rincian.</p>
                        </div>
                    )}
                </div>
                <div className="panel-b" style={{ display: 'flex', gap: 8 }}>
                    {step > 0 && <button className="btn" type="button" onClick={() => setStep(step - 1)}>Kembali</button>}
                    {step < 4 && <button className="btn btn-primary" type="button" onClick={() => setStep(step + 1)}>Lanjut</button>}
                    {step === 4 && <button className="btn btn-primary" disabled={form.processing} type="submit">{form.processing ? 'Menyimpan…' : 'Simpan draf'}</button>}
                </div>
            </form>
        </AppShell>
    );
}
