import { Link } from '@inertiajs/react';
import AppShell from '@/components/Shell';
import { Money, Status, Trend } from '@/components/ui';

interface Position { revenue: number; expense: number; net: number }
interface Props {
    period: { id: number; name: string; status_label: string } | null;
    prior_period?: string | null;
    scope: string;
    position: Position;
    prior: Position;
    cash: number;
    bank: number;
    cash_accounts: { id: number; name: string; unit: string; balance: number }[];
    bank_accounts: { id: number; name: string; unit: string; masked: string; balance: number }[];
    trend: { label: string; revenue: number; expense: number; net: number }[];
    units: { id: number; code: string; name: string; revenue: number; expense: number; net: number; utilization: number | null }[];
    activity: { id: number; action: string; actor: string | null; number: string; amount: number; description: string; transaction_id: number; at: string }[];
    queue: { id: number; number: string; unit: string; description: string; amount: number; status: string; status_label: string }[];
    alerts: { unit: string; account: string; state: string; label: string; ratio: number; href: string }[];
    blockers: string[];
}

function delta(current: number, previous: number) {
    const diff = current - previous;
    if (diff === 0) return 'Sama dengan periode sebelumnya';
    return `${diff > 0 ? 'Naik' : 'Turun'} dari periode sebelumnya`;
}

export default function Dashboard(props: Props) {
    return (
        <AppShell title="Dasbor">
            <div className="statement">
                <div className="statement-head">
                    <div>
                        <div className="page-kicker">Posisi keuangan</div>
                        <h1 className="page-title">{props.period?.name ?? 'Belum ada periode'} · {props.scope}</h1>
                    </div>
                    <div className="help">{props.period?.status_label}{props.prior_period ? ` · pembanding ${props.prior_period}` : ''}</div>
                </div>
                <div className="figures">
                    <div className="figure">
                        <div className="label">Pendapatan</div>
                        <Link className="value num" href={`/transactions?type=income`}><Money value={props.position.revenue} /></Link>
                        <div className="delta">{delta(props.position.revenue, props.prior.revenue)}</div>
                    </div>
                    <div className="figure">
                        <div className="label">Beban</div>
                        <Link className="value num" href="/analytics/expenses"><Money value={props.position.expense} /></Link>
                        <div className="delta">{delta(props.position.expense, props.prior.expense)}</div>
                    </div>
                    <div className="figure">
                        <div className="label">Neto</div>
                        <div className="value num"><Money value={props.position.net} /></div>
                        <div className="delta">{props.position.net >= 0 ? 'Surplus periode' : 'Defisit periode'}</div>
                    </div>
                </div>
            </div>

            <div className="layout-2">
                <section className="panel">
                    <div className="panel-h"><h2>Pendapatan dan beban</h2><span className="help">Enam periode terakhir</span></div>
                    <div className="panel-b">{props.trend.length === 0 ? <p className="help">Jurnal belum ada.</p> : <Trend rows={props.trend} />}</div>
                </section>
                <section className="panel">
                    <div className="panel-h"><h2>Kas dan bank</h2><span className="num"><Money value={props.cash + props.bank} /></span></div>
                    <div className="panel-b">
                        <div className="help" style={{ marginBottom: 8 }}>Kas <Money value={props.cash} /> · Bank <Money value={props.bank} /></div>
                        {props.cash_accounts.slice(0, 4).map((account) => (
                            <div key={`c${account.id}`} className="compare"><span>{account.unit} kas</span><span>{account.name}</span><Money value={account.balance} /></div>
                        ))}
                        {props.bank_accounts.slice(0, 4).map((account) => (
                            <div key={`b${account.id}`} className="compare"><span>{account.unit} bank</span><span>{account.masked}</span><Money value={account.balance} /></div>
                        ))}
                    </div>
                </section>
            </div>

            {props.units.length > 0 && (
                <section className="panel" style={{ marginTop: 16 }}>
                    <div className="panel-h"><h2>Kinerja unit</h2><Link href="/reports?report=unit_performance">Laporan</Link></div>
                    <table className="data">
                        <thead><tr><th>Unit</th><th className="right">Pendapatan</th><th className="right">Beban</th><th className="right">Neto</th><th>Anggaran</th></tr></thead>
                        <tbody>
                            {props.units.map((unit) => (
                                <tr key={unit.id}>
                                    <td><Link href={`/transactions?unit_id=${unit.id}`}>{unit.code}</Link> <span className="help">{unit.name}</span></td>
                                    <td className="right"><Money value={unit.revenue} /></td>
                                    <td className="right"><Link href={`/analytics/expenses?unit_id=${unit.id}`}><Money value={unit.expense} /></Link></td>
                                    <td className="right"><Money value={unit.net} /></td>
                                    <td>{unit.utilization === null ? '—' : <Status value={unit.utilization >= 100 ? 'exceeded' : unit.utilization >= 75 ? 'approaching' : 'normal'} label={`${unit.utilization}% terpakai`} />}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            )}

            <div className="layout-2">
                <section className="panel">
                    <div className="panel-h"><h2>Aktivitas</h2></div>
                    <table className="data">
                        <tbody>
                            {props.activity.length === 0 && <tr><td className="help">Belum ada jejak persetujuan.</td></tr>}
                            {props.activity.map((item) => (
                                <tr key={item.id}>
                                    <td><Link href={`/transactions/${item.transaction_id}`}>{item.number}</Link><div className="help">{item.description}</div></td>
                                    <td>{item.action}</td>
                                    <td className="hide-sm">{item.actor}</td>
                                    <td className="right"><Money value={item.amount} /></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
                <section className="panel">
                    <div className="panel-h"><h2>Menunggu keputusan</h2><Link href="/approvals">Antrean</Link></div>
                    <table className="data">
                        <tbody>
                            {props.queue.length === 0 && <tr><td className="help">Tidak ada transaksi menggantung.</td></tr>}
                            {props.queue.map((item) => (
                                <tr key={item.id}>
                                    <td><Link href={`/transactions/${item.id}`}>{item.number}</Link><div className="help">{item.unit} · {item.description}</div></td>
                                    <td><Status value={item.status} label={item.status_label} /></td>
                                    <td className="right"><Money value={item.amount} /></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {(props.alerts.length > 0 || props.blockers.length > 0) && (
                        <div className="panel-b">
                            {props.alerts.map((alert) => (
                                <div key={alert.href + alert.account}><Link href={alert.href}><Status value={alert.state} label={`${alert.unit} ${alert.account}: ${alert.label} (${alert.ratio}%)`} /></Link></div>
                            ))}
                            {props.blockers.map((blocker) => <p key={blocker} className="help">{blocker}</p>)}
                        </div>
                    )}
                </section>
            </div>
        </AppShell>
    );
}
