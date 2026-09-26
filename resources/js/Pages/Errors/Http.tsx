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
        <main className="login-main" style={{ minHeight: '100vh' }}>
            <div>
                <div className="page-kicker">{status}</div>
                <h1 className="page-title">{message.title}</h1>
                <p className="lede">{message.body}</p>
                <Link className="btn btn-primary" href="/dashboard">Kembali ke dasbor</Link>
            </div>
        </main>
    );
}
