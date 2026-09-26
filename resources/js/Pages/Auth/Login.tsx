import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

const units = ['RA', 'MI', 'DTA', 'MTs', 'MA', 'Pesantren', "Majelis Ta'lim", 'BLKK'];

export default function Login() {
    const form = useForm({ email: '', password: '', remember: false });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/login');
    };

    return (
        <div className="login">
            <aside className="login-aside">
                <div>
                    <div className="page-kicker" style={{ color: '#c8b48a' }}>Yayasan Tanwiriyyah</div>
                    <h1>Buku keuangan yang bisa ditelusuri.</h1>
                    <p style={{ maxWidth: 360, color: '#c9d5ce' }}>
                        Penerimaan, beban, kas, bank, dan anggaran delapan unit dalam satu jurnal. Angka yang diposting tidak diubah diam-diam.
                    </p>
                    <div className="unit-index">
                        {units.map((unit) => <span key={unit}>{unit}</span>)}
                    </div>
                </div>
                <p style={{ fontSize: 12, color: '#8ea199' }}>Akses mengikuti peran. Bendahara unit hanya melihat unitnya.</p>
            </aside>
            <main className="login-main">
                <form className="login-card" onSubmit={submit}>
                    <div className="page-kicker">Masuk</div>
                    <h2 className="page-title">Lanjutkan ke meja kerja</h2>
                    <div className="field" style={{ marginTop: 22 }}>
                        <span>Email</span>
                        <input type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} autoComplete="username" required />
                        {form.errors.email && <em className="error">{form.errors.email}</em>}
                    </div>
                    <div className="field" style={{ marginTop: 12 }}>
                        <span>Kata sandi</span>
                        <input type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} autoComplete="current-password" required />
                    </div>
                    <label style={{ display: 'flex', gap: 8, marginTop: 12, fontSize: 13 }}>
                        <input type="checkbox" checked={form.data.remember} onChange={(event) => form.setData('remember', event.target.checked)} />
                        Ingat sesi di perangkat ini
                    </label>
                    <button className="btn btn-primary" style={{ marginTop: 18 }} disabled={form.processing} type="submit">
                        {form.processing ? 'Memeriksa…' : 'Masuk'}
                    </button>
                </form>
            </main>
        </div>
    );
}
