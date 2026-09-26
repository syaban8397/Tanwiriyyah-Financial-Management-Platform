import { Link } from '@inertiajs/react';
import { ReactNode } from 'react';
import { formatRupiah } from '@/lib/money';
import type { Paginator } from '@/types';

export function PageHeader({ kicker, title, lede, action }: { kicker?: string; title: string; lede?: string; action?: ReactNode }) {
    return (
        <div className="page-head">
            <div>
                {kicker && <div className="page-kicker">{kicker}</div>}
                <h1 className="page-title">{title}</h1>
                {lede && <p className="lede">{lede}</p>}
            </div>
            {action}
        </div>
    );
}

export function Money({ value }: { value: number }) {
    return <span className="num">{formatRupiah(value)}</span>;
}

export function Status({ value, label }: { value: string; label: string }) {
    return <span className={`status ${value}`}><i />{label}</span>;
}

export function Empty({ title, body, action }: { title: string; body: string; action?: ReactNode }) {
    return (
        <div className="empty">
            <strong>{title}</strong>
            <p>{body}</p>
            {action}
        </div>
    );
}

export function Pager<T>({ paginator }: { paginator: Paginator<T> }) {
    if (paginator.total <= paginator.data.length && paginator.current_page === 1) return null;

    return (
        <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 12, fontSize: 13 }}>
            <span className="help">{paginator.total} baris</span>
            <span style={{ display: 'flex', gap: 8 }}>
                {paginator.prev_page_url && <Link href={paginator.prev_page_url}>Sebelumnya</Link>}
                {paginator.next_page_url && <Link href={paginator.next_page_url}>Berikutnya</Link>}
            </span>
        </div>
    );
}

export function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return (
        <label className="field">
            <span>{label}</span>
            {children}
            {error && <em className="error">{error}</em>}
        </label>
    );
}

function axisAmount(value: number): string {
    const abs = Math.abs(value);
    if (abs >= 1_000_000_000) return `${(value / 1_000_000_000).toLocaleString('id-ID', { maximumFractionDigits: 1 })} M`;
    if (abs >= 1_000_000) return `${(value / 1_000_000).toLocaleString('id-ID', { maximumFractionDigits: 1 })} jt`;
    if (abs >= 1_000) return `${Math.round(value / 1_000)} rb`;
    return String(Math.round(value));
}

export function Trend({ rows }: { rows: { label: string; revenue: number; expense: number; net?: number }[] }) {
    const max = Math.max(1, ...rows.flatMap((row) => [row.revenue, row.expense]));
    const width = 640;
    const height = 280;
    const padLeft = 56;
    const padRight = 16;
    const padTop = 18;
    const padBottom = 36;
    const innerWidth = width - padLeft - padRight;
    const innerHeight = height - padTop - padBottom;
    const base = padTop + innerHeight;
    const ticks = [0, 0.5, 1];
    const xAt = (index: number) => padLeft + (rows.length <= 1 ? innerWidth / 2 : (innerWidth * index) / (rows.length - 1));
    const yAt = (value: number) => padTop + innerHeight - (value / max) * innerHeight;
    const line = (key: 'revenue' | 'expense') => rows.map((row, index) => `${index === 0 ? 'M' : 'L'} ${xAt(index)} ${yAt(row[key])}`).join(' ');
    const area = (key: 'revenue' | 'expense') => `${line(key)} L ${xAt(rows.length - 1)} ${base} L ${xAt(0)} ${base} Z`;

    return (
        <div className="chart" aria-label="Pendapatan dibanding beban">
            <div className="chart-legend">
                <span><i className="in" /> Pendapatan</span>
                <span><i className="out" /> Beban</span>
            </div>
            <svg className="chart-svg" viewBox={`0 0 ${width} ${height}`} role="img">
                {ticks.map((tick) => {
                    const y = padTop + innerHeight - tick * innerHeight;
                    return (
                        <g key={tick}>
                            <line className="grid" x1={padLeft} x2={width - padRight} y1={y} y2={y} />
                            <text className="axis" x={padLeft - 8} y={y + 4} textAnchor="end">{axisAmount(max * tick)}</text>
                        </g>
                    );
                })}
                {rows.length > 0 && (
                    <>
                        <path className="area-in" d={area('revenue')} />
                        <path className="area-out" d={area('expense')} />
                        <path className="line-in" d={line('revenue')} />
                        <path className="line-out" d={line('expense')} />
                    </>
                )}
                {rows.map((row, index) => (
                    <g key={row.label}>
                        <title>{`${row.label}: pendapatan ${formatRupiah(row.revenue)}, beban ${formatRupiah(row.expense)}`}</title>
                        <circle className="dot-in" cx={xAt(index)} cy={yAt(row.revenue)} r="4.5" />
                        <circle className="dot-out" cx={xAt(index)} cy={yAt(row.expense)} r="4.5" />
                        <text className="axis month" x={xAt(index)} y={height - 12} textAnchor="middle">{row.label.replace(' 2026', '')}</text>
                    </g>
                ))}
            </svg>
        </div>
    );
}

export function BudgetMeter({ planned, actual, committed }: { planned: number; actual: number; committed: number }) {
    const base = Math.max(planned, actual + committed, 1);
    return (
        <div>
            <div className="compare"><span>Rencana</span><strong className="num">{formatRupiah(planned)}</strong><span /></div>
            <div className="compare"><span>Realisasi</span><strong className="num">{formatRupiah(actual)}</strong><span /></div>
            <div className="compare"><span>Terikat</span><strong className="num">{formatRupiah(committed)}</strong><span /></div>
            <div className="compare"><span>Sisa</span><strong className="num">{formatRupiah(planned - actual - committed)}</strong><span /></div>
            <div className="meter compare" style={{ marginTop: 8 }}>
                <div className="meter" style={{ gridColumn: '1 / -1' }}>
                    <i className="actual" style={{ width: `${(actual / base) * 100}%` }} />
                    <i className="committed" style={{ width: `${(committed / base) * 100}%` }} />
                </div>
            </div>
        </div>
    );
}

export function Errors({ errors }: { errors?: Record<string, string> }) {
    if (!errors) return null;
    const messages = Object.values(errors).filter(Boolean);
    if (messages.length === 0) return null;
    return <div className="toast" style={{ position: 'static', marginBottom: 12 }}>{messages[0]}</div>;
}
