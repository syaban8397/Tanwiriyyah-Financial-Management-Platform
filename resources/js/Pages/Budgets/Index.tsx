import { Link } from '@inertiajs/react';
import AppShell from '@/components/Shell';
import { PageHeader, Pager, Status } from '@/components/ui';
import type { Paginator } from '@/types';

export default function Index({ budgets, can_create }: { budgets: Paginator<{ id: number; name: string; unit: string; period: string; status: string }>; can_create: boolean }) {
    return (
        <AppShell title="Anggaran">
            <PageHeader kicker="Pengendalian" title="Anggaran" lede="Rencana dibanding realisasi dan komitmen yang belum diposting." action={can_create ? <Link className="btn btn-primary" href="/budgets/create">Susun anggaran</Link> : undefined} />
            <table className="data">
                <thead><tr><th>Nama</th><th>Unit</th><th>Periode</th><th>Status</th></tr></thead>
                <tbody>{budgets.data.map((budget) => <tr key={budget.id}><td><Link href={`/budgets/${budget.id}`}>{budget.name}</Link></td><td>{budget.unit}</td><td>{budget.period}</td><td><Status value={budget.status === 'approved' ? 'approved' : 'draft'} label={budget.status === 'approved' ? 'Disetujui' : 'Draf'} /></td></tr>)}</tbody>
            </table>
            <Pager paginator={budgets} />
        </AppShell>
    );
}
