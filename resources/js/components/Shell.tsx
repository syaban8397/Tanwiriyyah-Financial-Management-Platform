import { Head, Link, router, usePage } from '@inertiajs/react';
import { FormEvent, ReactNode, useEffect, useMemo, useState } from 'react';
import type { SharedProps } from '@/types';

export default function AppShell({ title, children }: { title: string; children: ReactNode }) {
    const { auth, navigation, unit_options, context_unit_id, notifications, flash } = usePage<SharedProps>().props;
    const url = usePage().url;
    const [open, setOpen] = useState(false);
    const [closed, setClosed] = useState(() => window.localStorage.getItem('tw-sidebar') === 'closed');
    const cursor = useMemo(() => ({ current: Number(window.sessionStorage.getItem('tw-event') || 0) }), []);
    const [palette, setPalette] = useState(false);
    const [help, setHelp] = useState(false);
    const [notes, setNotes] = useState(false);
    const [calendar, setCalendar] = useState(false);
    const [toast, setToast] = useState<string | null>(null);
    const path = url.split('?')[0];
    const todayLabel = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date());

    useEffect(() => {
        if (flash?.success) setToast(flash.success);
        if (flash?.error) setToast(flash.error);
    }, [flash?.success, flash?.error]);

    useEffect(() => {
        if (!toast) return;
        const timer = window.setTimeout(() => setToast(null), 4200);
        return () => window.clearTimeout(timer);
    }, [toast]);

    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                setPalette(true);
            }
            if (event.key === 'Escape') {
                setPalette(false);
                setHelp(false);
                setNotes(false);
                setCalendar(false);
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    useEffect(() => {
        let source: EventSource | null = null;
        let timer: number | undefined;
        let closed = false;

        const connect = () => {
            if (closed) return;
            const after = cursor.current > 0 ? `?after=${cursor.current}` : '';
            source = new EventSource(`/realtime/stream${after}`);
            source.onmessage = (event) => {
                if (event.lastEventId) {
                    cursor.current = Number(event.lastEventId);
                    window.sessionStorage.setItem('tw-event', event.lastEventId);
                }
                const data = JSON.parse(event.data) as { type?: string };
                if (!data.type) return;
                router.reload();
            };
            source.onerror = () => {
                source?.close();
                timer = window.setTimeout(connect, 2000);
            };
        };

        connect();

        return () => {
            closed = true;
            source?.close();
            if (timer) window.clearTimeout(timer);
        };
    }, []);

    const user = auth?.user;
    const toggleSidebar = () => {
        if (window.matchMedia('(max-width: 960px)').matches) {
            setOpen((value) => !value);
            return;
        }
        setClosed((value) => {
            const next = !value;
            window.localStorage.setItem('tw-sidebar', next ? 'closed' : 'open');
            return next;
        });
    };

    return (
        <>
            <Head title={title} />
            <div className={closed ? 'shell is-closed' : 'shell'}>
                {open && <button className="scrim" type="button" aria-label="Tutup menu" onClick={() => setOpen(false)} />}
                <aside className={open ? 'sidebar open' : 'sidebar'}>
                    <div className="brand">
                        <div className="brand-lockup">
                            <img className="brand-logo" src="/brand/tanwiriyyah-logo.jpg" alt="" />
                            <div>
                                <b>TANWIRIYYAH</b>
                                <span>Yayasan</span>
                            </div>
                        </div>
                    </div>
                    <nav className="nav" aria-label="Utama">
                        {navigation.map((group) => (
                            <div key={group.id}>
                                {group.label && <div className="nav-label">{group.label}</div>}
                                {group.items.map((item) => (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        className={path === item.href || path.startsWith(item.href + '/') ? 'active' : ''}
                                        onClick={() => setOpen(false)}
                                    >
                                        <span className="nav-glyph" aria-hidden="true">{item.label.slice(0, 1)}</span>
                                        <span className="nav-text">{item.label}</span>
                                        {!!item.badge && <span className="badge">{item.badge}</span>}
                                    </Link>
                                ))}
                            </div>
                        ))}
                    </nav>
                </aside>
                <div className="workspace">
                    <header className="topbar">
                        <button className="icon-btn menu-toggle" type="button" aria-expanded={open || !closed} aria-label="Buka atau tutup menu" onClick={toggleSidebar}>
                            <MenuLines />
                        </button>
                        <div className="topbar-brand">
                            <img className="brand-logo" src="/brand/tanwiriyyah-logo.jpg" alt="" />
                            <span>TANWIRIYYAH</span>
                        </div>
                        <button className="search-btn" type="button" onClick={() => setPalette(true)}>
                            <span>Cari transaksi, unit, akun…</span>
                            <span className="kbd">Ctrl K</span>
                        </button>
                        <div className="topbar-end">
                            {user?.unit_id === null && (
                                <form
                                    onChange={(event) => {
                                        const data = new FormData(event.currentTarget);
                                        router.post('/context/unit', { unit_id: data.get('unit_id') }, { preserveScroll: true });
                                    }}
                                >
                                    <label className="sr-only" htmlFor="unit-context">Unit</label>
                                    <select id="unit-context" name="unit_id" defaultValue={context_unit_id ?? 'all'} aria-label="Konteks unit">
                                        <option value="all">Semua unit</option>
                                        {unit_options.map((unit) => (
                                            <option key={unit.id} value={unit.id}>{unit.code}</option>
                                        ))}
                                    </select>
                                </form>
                            )}
                            {user?.unit && <span className="unit-pill">{user.unit.code}</span>}
                            <div className="menu">
                                <button className="date-btn" type="button" aria-expanded={calendar} onClick={() => { setCalendar((value) => !value); setNotes(false); }}>
                                    <CalendarIcon />
                                    <span>{todayLabel}</span>
                                </button>
                                {calendar && <CalendarPop />}
                            </div>
                            <div className="menu">
                                <button className="icon-btn" type="button" aria-label="Notifikasi" aria-expanded={notes} onClick={() => { setNotes((value) => !value); setCalendar(false); }}>
                                    <Bell />
                                    {notifications.unread > 0 && <span className="badge">{notifications.unread}</span>}
                                </button>
                                {notes && (
                                    <div className="menu-pop notes-pop">
                                        <div className="notes-head">
                                            <strong>Notifikasi</strong>
                                            <Link href="/notifications" onClick={() => setNotes(false)}>Semua</Link>
                                        </div>
                                        {notifications.preview.length === 0 && <p className="help">Tidak ada notifikasi.</p>}
                                        {notifications.preview.map((item) => (
                                            <Link key={item.id} href={`/notifications/${item.id}/read`} method="post" as="button" className={item.read ? 'note read' : 'note'} onClick={() => setNotes(false)}>
                                                <strong>{item.title}</strong>
                                                <span>{item.body}</span>
                                            </Link>
                                        ))}
                                    </div>
                                )}
                            </div>
                            <button className="btn btn-quiet" type="button" onClick={() => setHelp(true)}>Bantuan</button>
                            <div className="who">
                                <strong>{user?.name}</strong>
                                <span>{user?.role}</span>
                            </div>
                            <Link className="btn top-logout" href="/logout" method="post" as="button">Keluar</Link>
                        </div>
                    </header>
                    <main className="content">{children}</main>
                </div>
            </div>
            {palette && <CommandPalette onClose={() => setPalette(false)} />}
            {help && <HelpDrawer role={user?.role ?? ''} onClose={() => setHelp(false)} />}
            {toast && <div className="toast" role="status">{toast}</div>}
        </>
    );
}

function CalendarPop() {
    const [cursor, setCursor] = useState(() => new Date());
    const year = cursor.getFullYear();
    const month = cursor.getMonth();
    const firstWeekday = (new Date(year, month, 1).getDay() + 6) % 7;
    const count = new Date(year, month + 1, 0).getDate();
    const cells: Array<number | null> = [
        ...Array.from({ length: firstWeekday }, () => null),
        ...Array.from({ length: count }, (_, index) => index + 1),
    ];
    const raw = new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' }).format(cursor);
    const label = raw.charAt(0).toUpperCase() + raw.slice(1);
    const today = new Date();

    return (
        <div className="calendar-pop" role="dialog" aria-label="Kalender">
            <div className="calendar-head">
                <button type="button" aria-label="Bulan sebelumnya" onClick={() => setCursor(new Date(year, month - 1, 1))}>‹</button>
                <strong>{label}</strong>
                <button type="button" aria-label="Bulan berikutnya" onClick={() => setCursor(new Date(year, month + 1, 1))}>›</button>
            </div>
            <div className="calendar-grid">
                {['Sn', 'Sl', 'Rb', 'Km', 'Jm', 'Sb', 'Mg'].map((day) => <span key={day} className="dow">{day}</span>)}
                {cells.map((day, index) => {
                    const current = day !== null && day === today.getDate() && month === today.getMonth() && year === today.getFullYear();
                    return <span key={index} className={current ? 'day today' : 'day'}>{day ?? ''}</span>;
                })}
            </div>
        </div>
    );
}

function Bell() {
    return (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M6 9a6 6 0 1 1 12 0c0 7 2 7 2 7H4s2 0 2-7Z" stroke="currentColor" strokeWidth="1.6" />
            <path d="M10 19a2 2 0 0 0 4 0" stroke="currentColor" strokeWidth="1.6" />
        </svg>
    );
}

function CalendarIcon() {
    return (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <rect x="3.5" y="5" width="17" height="15" rx="2" stroke="currentColor" strokeWidth="1.6" />
            <path d="M3.5 10h17M8 3.5V7M16 3.5V7" stroke="currentColor" strokeWidth="1.6" />
        </svg>
    );
}

function MenuLines() {
    return (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M4 7h16M4 12h16M4 19h16" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" />
        </svg>
    );
}

function CommandPalette({ onClose }: { onClose: () => void }) {
    const [query, setQuery] = useState('');
    const [groups, setGroups] = useState<{ category: string; items: { label: string; meta: string; href: string }[] }[]>([]);
    const recent = useMemo(() => JSON.parse(localStorage.getItem('tw-recent') || '[]') as string[], []);

    useEffect(() => {
        if (query.trim().length < 2) {
            setGroups([]);
            return;
        }
        const handle = window.setTimeout(async () => {
            const response = await fetch(`/search?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } });
            const body = await response.json();
            setGroups(body.groups ?? []);
        }, 180);
        return () => window.clearTimeout(handle);
    }, [query]);

    const remember = (term: string) => {
        const next = [term, ...recent.filter((item) => item !== term)].slice(0, 5);
        localStorage.setItem('tw-recent', JSON.stringify(next));
    };

    return (
        <div className="overlay" onClick={onClose}>
            <div className="palette" onClick={(event) => event.stopPropagation()} role="dialog" aria-label="Pencarian">
                <input autoFocus placeholder="Ketik nomor, nama unit, atau akun" value={query} onChange={(event) => setQuery(event.target.value)} />
                {query.trim().length < 2 && recent.length > 0 && (
                    <div>
                        <div className="cat">Terakhir</div>
                        {recent.map((item) => (
                            <button key={item} className="hit" type="button" onClick={() => setQuery(item)}>{item}</button>
                        ))}
                    </div>
                )}
                {groups.map((group) => (
                    <div key={group.category}>
                        <div className="cat">{group.category}</div>
                        {group.items.map((item) => (
                            <Link key={item.href + item.label} href={item.href} onClick={() => { remember(query); onClose(); }}>
                                <strong>{item.label}</strong>
                                <div className="help">{item.meta}</div>
                            </Link>
                        ))}
                    </div>
                ))}
                {query.trim().length >= 2 && groups.length === 0 && <p className="help" style={{ padding: 14 }}>Tidak ada hasil dalam wewenang Anda.</p>}
            </div>
        </div>
    );
}

function HelpDrawer({ role, onClose }: { role: string; onClose: () => void }) {
    return (
        <div className="overlay" onClick={onClose}>
            <div className="palette" onClick={(event) => event.stopPropagation()} role="dialog" aria-label="Bantuan">
                <div className="panel-h"><h2>Cara kerja singkat</h2><button className="btn btn-quiet" type="button" onClick={onClose}>Tutup</button></div>
                <div className="panel-b help">
                    <p>Peran Anda: {role}.</p>
                    <p>Bendahara unit membuat draf, melampirkan bukti, lalu mengajukan. Bendahara yayasan meninjau, menyetujui, dan memposting. Transaksi yang sudah diposting tidak diubah diam-diam; gunakan penyesuaian.</p>
                    <p>Transfer kas ke bank memindahkan saldo, bukan menambah pendapatan. Angka di dasbor berasal dari jurnal.</p>
                </div>
            </div>
        </div>
    );
}

export function useSubmit(action: string) {
    return (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        router.post(action, Object.fromEntries(form.entries()));
    };
}
