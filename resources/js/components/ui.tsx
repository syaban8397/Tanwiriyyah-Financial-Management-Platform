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

export function Trend({ rows }: { rows: { label: string; revenue: number; expense: number; net?: number }[] }) {
    const max = Math.max(1, ...rows.flatMap((row) => [row.revenue, row.expense]));

    return (
        <div className="bars" aria-label="Pendapatan dibanding beban">
            {rows.map((row) => (
                <div key={row.label} className="bar-row">
                    <span>{row.label.replace(' 2026', '')}</span>
                    <div className="track" title={`Pendapatan ${formatRupiah(row.revenue)}, beban ${formatRupiah(row.expense)}`}>
                        <span className="in" style={{ width: `${(row.revenue / max) * 100}%` }} />
                        <span className="out" style={{ width: `${(row.expense / max) * 100}%`, opacity: 0.85 }} />
                    </div>
                    <span className="num right">{formatRupiah(row.net ?? row.revenue - row.expense)}</span>
                </div>
            ))}
            <div className="help">Garis hijau pendapatan. Garis pasir beban. Angka kanan adalah neto.</div>
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
