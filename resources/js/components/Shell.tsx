import { Head, Link, router, usePage } from '@inertiajs/react';
import { FormEvent, ReactNode, useEffect, useMemo, useState } from 'react';
import type { SharedProps } from '@/types';

export default function AppShell({ title, children }: { title: string; children: ReactNode }) {
    const { auth, navigation, unit_options, context_unit_id, notifications, flash } = usePage<SharedProps>().props;
    const url = usePage().url;
    const [open, setOpen] = useState(false);
    const [collapsed, setCollapsed] = useState(() => window.localStorage.getItem('tw-sidebar') === 'collapsed');
    const cursor = useMemo(() => ({ current: Number(window.sessionStorage.getItem('tw-event') || 0) }), []);
    const [palette, setPalette] = useState(false);
    const [help, setHelp] = useState(false);
    const [profile, setProfile] = useState(false);
    const [notes, setNotes] = useState(false);
    const [toast, setToast] = useState<string | null>(null);
    const path = url.split('?')[0];

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

    return (
        <>
            <Head title={title} />
            <div className={collapsed ? 'shell is-collapsed' : 'shell'}>
                {open && <button className="scrim" type="button" aria-label="Tutup menu" onClick={() => setOpen(false)} />}
                <aside className={open ? 'sidebar open' : 'sidebar'}>
                    <div className="brand">
                        <b>TANWIRIYYAH</b>
                        <span>Keuangan yayasan</span>
                        <i />
                        <button className="btn btn-quiet collapse-toggle" type="button" onClick={() => setCollapsed((value) => {
                            const next = !value;
                            window.localStorage.setItem('tw-sidebar', next ? 'collapsed' : 'open');
                            return next;
                        })}>
                            {collapsed ? 'Lebarkan' : 'Ciutkan'}
                        </button>
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
                        <button className="btn mobile-only" type="button" onClick={() => setOpen((value) => !value)} aria-label="Menu">
                            Menu
                        </button>
                        <button className="search-btn" type="button" onClick={() => setPalette(true)}>
                            <span>Cari transaksi, unit, akun…</span>
                            <span className="kbd">Ctrl K</span>
                        </button>
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
                        {user?.unit && <span className="text-sm" style={{ color: 'var(--color-muted)' }}>{user.unit.code}</span>}
                        <button className="btn btn-quiet" type="button" onClick={() => setNotes((value) => !value)} aria-label="Notifikasi">
                            Notifikasi{notifications.unread > 0 ? ` (${notifications.unread})` : ''}
                        </button>
                        <button className="btn btn-quiet" type="button" onClick={() => setHelp(true)}>Bantuan</button>
                        <div className="menu">
                            <button className="btn" type="button" onClick={() => setProfile((value) => !value)}>
                                {user?.name}
                            </button>
                            {profile && (
                                <div className="menu-pop">
                                    <div style={{ padding: '8px 10px', color: 'var(--color-muted)', fontSize: 12 }}>{user?.role}</div>
                                    <Link href="/logout" method="post" as="button">Keluar</Link>
                                </div>
                            )}
                        </div>
                    </header>
                    {notes && (
                        <div className="panel" style={{ margin: '0 20px', position: 'absolute', right: 20, top: 56, width: 320, zIndex: 30 }}>
                            <div className="panel-h"><h2>Notifikasi</h2><Link href="/notifications">Semua</Link></div>
                            <div className="panel-b">
                                {notifications.preview.length === 0 && <p className="help">Tidak ada notifikasi.</p>}
                                {notifications.preview.map((item) => (
                                    <Link key={item.id} href={`/notifications/${item.id}/read`} method="post" as="button" className="hit" style={{ display: 'block', textAlign: 'left', width: '100%', background: 'transparent', border: 0, padding: '8px 0', cursor: 'pointer' }}>
                                        <strong>{item.title}</strong>
                                        <div className="help">{item.body}</div>
                                    </Link>
                                ))}
                            </div>
                        </div>
                    )}
                    <main className="content">{children}</main>
                </div>
            </div>
            {palette && <CommandPalette onClose={() => setPalette(false)} />}
            {help && <HelpDrawer role={user?.role ?? ''} onClose={() => setHelp(false)} />}
            {toast && <div className="toast" role="status">{toast}</div>}
        </>
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
