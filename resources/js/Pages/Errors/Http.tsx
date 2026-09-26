import { Link } from '@inertiajs/react';

const copy: Record<number, { title: string; body: string }> = {
    403: { title: 'Akses ditolak', body: 'Peran Anda tidak mencakup data atau tindakan ini.' },
    404: { title: 'Tidak ditemukan', body: 'Halaman atau catatan itu tidak ada.' },
    419: { title: 'Sesi berakhir', body: 'Muat ulang halaman, lalu masuk kembali.' },
    500: { title: 'Tidak dapat memuat data', body: 'Coba lagi. Rincian teknis tidak ditampilkan.' },
    503: { title: 'Sedang tidak tersedia', body: 'Layanan sedang beristirahat. Coba beberapa saat lagi.' },
};

export default function Http({ status }: { status: number }) {
    const message = copy[status] ?? copy[500];

    return (
        <main className="status-screen">
            <div className="panel">
                <div className="panel-b">
                    <img className="status-logo" src="/brand/tanwiriyyah-logo.jpg" alt="Logo Yayasan Tanwiriyyah" />
                    <div className="page-kicker">Yayasan Tanwiriyyah · {status}</div>
                    <h1 className="page-title">{message.title}</h1>
                    <p className="lede">{message.body}</p>
                    <Link className="btn btn-primary" href="/dashboard">Kembali ke dasbor</Link>
                </div>
            </div>
        </main>
    );
}
