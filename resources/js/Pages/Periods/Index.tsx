import { router, useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';
import AppShell from '@/components/Shell';
import { PageHeader, Status } from '@/components/ui';

export default function Index({ periods, can_open, can_close }: { periods: { id: number; name: string; starts_on: string; ends_on: string; status: string; status_label: string; blockers: string[] }[]; can_open: boolean; can_close: boolean }) {
    const errors = usePage().props.errors as Record<string, string>;
    const form = useForm({ name: '', starts_on: '', ends_on: '' });
    const submit = (event: FormEvent) => { event.preventDefault(); form.post('/periods'); };

    return (
        <AppShell title="Periode">
            <PageHeader kicker="Pengendalian" title="Periode keuangan" lede="Periode terkunci menolak perubahan biasa. Gunakan penyesuaian pada periode yang masih terbuka." />
            {errors.period && <p className="toast" style={{ position: 'static', marginBottom: 12 }}>{errors.period}</p>}
            {periods.map((period) => (
                <section key={period.id} className="panel" style={{ marginBottom: 10 }}>
                    <div className="panel-h"><h2>{period.name}</h2><Status value={period.status === 'locked' ? 'posted' : period.status === 'closing' ? 'under_review' : 'draft'} label={period.status_label} /></div>
                    <div className="panel-b">
                        <div className="help">{period.starts_on} — {period.ends_on}</div>
                        {period.blockers.map((blocker) => <p key={blocker}>{blocker}</p>)}
                        {can_close && period.status !== 'locked' && <button className="btn" type="button" onClick={() => router.post(`/periods/${period.id}/close`)}>Tutup periode</button>}
                    </div>
                </section>
            ))}
            {can_open && (
                <form onSubmit={submit} className="grid-form">
                    <label className="field"><span>Nama</span><input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></label>
                    <label className="field"><span>Mulai</span><input type="date" value={form.data.starts_on} onChange={(e) => form.setData('starts_on', e.target.value)} /></label>
                    <label className="field"><span>Selesai</span><input type="date" value={form.data.ends_on} onChange={(e) => form.setData('ends_on', e.target.value)} /></label>
                    <button className="btn btn-primary">Buka periode</button>
                </form>
            )}
        </AppShell>
    );
}
