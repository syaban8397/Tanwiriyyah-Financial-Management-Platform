import { router } from '@inertiajs/react';
import AppShell from '@/components/Shell';
import { BudgetMeter, Money, Status } from '@/components/ui';

export default function Show({ budget, can_approve }: { budget: { id: number; name: string; status: string; unit: string; period: string; lines: { id: number; code: string; name: string; planned: number; actual: number; committed: number; remaining: number; ratio: number; state: string; state_label: string }[] }; can_approve: boolean }) {
    const totals = budget.lines.reduce((sum, line) => ({ planned: sum.planned + line.planned, actual: sum.actual + line.actual, committed: sum.committed + line.committed }), { planned: 0, actual: 0, committed: 0 });

    return (
        <AppShell title={budget.name}>
            <div className="page-kicker">{budget.unit} · {budget.period}</div>
            <h1 className="page-title">{budget.name}</h1>
            <div className="layout-2">
                <section className="panel panel-b"><BudgetMeter {...totals} /></section>
                <section className="panel">
                    <table className="data">
                        <thead><tr><th>Akun</th><th className="right">Rencana</th><th className="right">Realisasi</th><th>Status</th></tr></thead>
                        <tbody>
                            {budget.lines.map((line) => (
                                <tr key={line.id}><td>{line.code} {line.name}</td><td className="right"><Money value={line.planned} /></td><td className="right"><Money value={line.actual} /></td><td><Status value={line.state} label={`${line.state_label} ${line.ratio}%`} /></td></tr>
                            ))}
                        </tbody>
                    </table>
                    {can_approve && <div className="panel-b"><button className="btn btn-primary" type="button" onClick={() => router.post(`/budgets/${budget.id}/approve`)}>Setujui anggaran</button></div>}
                </section>
            </div>
        </AppShell>
    );
}
