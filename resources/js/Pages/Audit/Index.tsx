import { router } from '@inertiajs/react';
import AppShell from '@/components/Shell';
import { PageHeader, Pager } from '@/components/ui';
import type { Paginator } from '@/types';

export default function Index({ logs, filters }: { logs: Paginator<{ id: number; at: string; user: string | null; role: string | null; unit: string | null; action: string; entity_type: string; entity_id: number | null; ip: string | null }>; filters: { q?: string } }) {
    return (
        <AppShell title="Jejak audit">
            <PageHeader kicker="Audit" title="Jejak audit" lede="Siapa mengubah apa, dari peran mana, dan kapan." />
            <input placeholder="Cari aksi" defaultValue={filters.q ?? ''} onBlur={(e) => router.get('/audit', { q: e.target.value }, { preserveState: true })} />
            <table className="data">
                <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Entitas</th><th className="hide-sm">IP</th></tr></thead>
                <tbody>{logs.data.map((log) => <tr key={log.id}><td>{log.at?.replace('T', ' ').slice(0, 16)}</td><td>{log.user}<div className="help">{log.role} {log.unit}</div></td><td>{log.action}</td><td>{log.entity_type} {log.entity_id}</td><td className="hide-sm">{log.ip}</td></tr>)}</tbody>
            </table>
            <Pager paginator={logs} />
        </AppShell>
    );
}
