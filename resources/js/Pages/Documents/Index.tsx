import AppShell from '@/components/Shell';
import { Empty, PageHeader, Pager } from '@/components/ui';
import type { Paginator } from '@/types';

export default function Index({ documents }: { documents: Paginator<{ id: number; name: string; kind_label: string; number: string; transaction_id: number; description: string }> }) {
    return (
        <AppShell title="Dokumen">
            <PageHeader kicker="Pengendalian" title="Dokumen" lede="Berkas hanya dapat diunduh oleh pengguna yang berwenang atas transaksinya." />
            {documents.data.length === 0 ? <Empty title="Belum ada dokumen." body="Lampirkan invoice atau kuitansi saat mencatat transaksi." /> : (
                <table className="data"><thead><tr><th>Berkas</th><th>Jenis</th><th>Transaksi</th></tr></thead><tbody>{documents.data.map((doc) => <tr key={doc.id}><td><a href={`/documents/${doc.id}`}>{doc.name}</a></td><td>{doc.kind_label}</td><td><a href={`/transactions/${doc.transaction_id}`}>{doc.number}</a></td></tr>)}</tbody></table>
            )}
            <Pager paginator={documents} />
        </AppShell>
    );
}
