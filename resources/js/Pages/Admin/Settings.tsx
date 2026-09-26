import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import AppShell from '@/components/Shell';
import { PageHeader } from '@/components/ui';

export default function Settings({ settings }: { settings: { prevent_self_approval: boolean; budget_thresholds: number[]; organization_name: string } }) {
    const form = useForm({
        prevent_self_approval: settings.prevent_self_approval,
        budget_thresholds: settings.budget_thresholds.join(','),
        organization_name: settings.organization_name,
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            budget_thresholds: String(data.budget_thresholds).split(',').map((item) => Number(item.trim())).filter(Boolean),
        }));
        form.put('/admin/settings');
    };

    return (
        <AppShell title="Pengaturan">
            <PageHeader kicker="Administrasi" title="Pengaturan" lede="Ambang anggaran dan larangan menyetujui transaksi sendiri." />
            <form onSubmit={submit} className="grid-form">
                <label className="field"><span>Nama organisasi</span><input value={form.data.organization_name} onChange={(e) => form.setData('organization_name', e.target.value)} /></label>
                <label className="field"><span>Ambang persen, pisahkan koma</span><input value={form.data.budget_thresholds} onChange={(e) => form.setData('budget_thresholds', e.target.value)} /></label>
                <label style={{ display: 'flex', gap: 8 }}><input type="checkbox" checked={form.data.prevent_self_approval} onChange={(e) => form.setData('prevent_self_approval', e.target.checked)} /> Cegah pembuat menyetujui transaksinya</label>
                <button className="btn btn-primary">Simpan</button>
            </form>
        </AppShell>
    );
}
