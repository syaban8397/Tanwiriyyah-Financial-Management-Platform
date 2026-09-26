import { Link, router } from '@inertiajs/react';
import AppShell from '@/components/Shell';
import { PageHeader, Pager } from '@/components/ui';
import type { Paginator } from '@/types';

export default function Index({ items }: { items: Paginator<{ id: string; title: string; body: string; href: string; read: boolean; at: string | null }> }) {
    return (
        <AppShell title="Notifikasi">
            <PageHeader title="Notifikasi" action={<button className="btn" type="button" onClick={() => router.post('/notifications/read-all')}>Tandai dibaca</button>} />
            {items.data.length === 0 ? <p className="help">Kotak masuk kosong.</p> : items.data.map((item) => (
                <Link key={item.id} href={`/notifications/${item.id}/read`} method="post" style={{ display: 'block', padding: '10px 0', borderBottom: '1px solid var(--color-line)' }}>
                    <strong>{item.title}</strong> {!item.read && <span className="help">baru</span>}
                    <div className="help">{item.body}</div>
                </Link>
            ))}
            <Pager paginator={items} />
        </AppShell>
    );
}
