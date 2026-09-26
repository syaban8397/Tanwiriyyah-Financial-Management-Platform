import { useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

const units = [
    ['01', 'RA', 'Raudhatul Athfal'],
    ['02', 'MI', 'Madrasah Ibtidaiyah'],
    ['03', 'DTA', 'Diniyah Takmiliyah'],
    ['04', 'MTs', 'Madrasah Tsanawiyah'],
    ['05', 'MA', 'Madrasah Aliyah'],
    ['06', 'PP', 'Pondok Pesantren'],
    ['07', 'MT', "Majelis Ta'lim"],
    ['08', 'BLKK', 'Balai Latihan Kerja'],
];

export default function Login() {
    const form = useForm({ email: '', password: '', remember: false });
    const [visible, setVisible] = useState(false);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/login');
    };

    return (
        <div className="login">
            <aside className="login-aside">
                <img className="login-logo" src="/brand/tanwiriyyah-logo.jpg" alt="Logo Yayasan Tanwiriyyah" />
                <div className="login-lead">
                    <p className="login-place">Sindanglaka, Karangtengah, Cianjur</p>
                    <h1>Menyinari dunia, dicintai semesta.</h1>
                    <i className="brass-rule" />
                    <p>
                        Buku keuangan Yayasan Madrasah Tanwiriyyah. Penerimaan, beban, kas, dan anggaran unit pendidikan di lingkungan pesantren, dalam satu jurnal.
                    </p>
                </div>
                <ol className="unit-roster">
                    {units.map(([index, code, name]) => (
                        <li key={code}>
                            <span>{index}</span>
                            <strong>{code}</strong>
                            <em>{name}</em>
                        </li>
                    ))}
                </ol>
            </aside>
            <main className="login-main">
                <form className="login-card" onSubmit={submit}>
                    <img className="login-form-logo" src="/brand/tanwiriyyah-logo.jpg" alt="Logo Yayasan Tanwiriyyah" />
                    <div className="page-kicker">Yayasan Tanwiriyyah</div>
                    <h2 className="page-title">Masuk</h2>
                    <p className="lede">Meja kerja bendahara unit dan bendahara yayasan.</p>
                    <div className="field" style={{ marginTop: 22 }}>
                        <span>Email</span>
                        <input type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} autoComplete="username" required />
                        {form.errors.email && <em className="error">{form.errors.email}</em>}
                    </div>
                    <div className="field" style={{ marginTop: 14 }}>
                        <span>Kata sandi</span>
                        <div className="password-wrap">
                            <input
                                type={visible ? 'text' : 'password'}
                                value={form.data.password}
                                onChange={(event) => form.setData('password', event.target.value)}
                                autoComplete="current-password"
                                required
                            />
                            <button
                                className="password-toggle"
                                type="button"
                                aria-pressed={visible}
                                aria-label={visible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
                                onClick={() => setVisible((value) => !value)}
                            >
                                {visible ? <EyeOff /> : <Eye />}
                            </button>
                        </div>
                    </div>
                    <div className="login-actions">
                        <label>
                            <input type="checkbox" checked={form.data.remember} onChange={(event) => form.setData('remember', event.target.checked)} />
                            Ingat sesi di perangkat ini
                        </label>
                        <button className="btn btn-primary" disabled={form.processing} type="submit">
                            {form.processing ? 'Memeriksa…' : 'Masuk'}
                        </button>
                    </div>
                </form>
            </main>
        </div>
    );
}

function Eye() {
    return (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" stroke="currentColor" strokeWidth="1.6" />
            <circle cx="12" cy="12" r="3" stroke="currentColor" strokeWidth="1.6" />
        </svg>
    );
}

function EyeOff() {
    return (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M3 3l18 18" stroke="currentColor" strokeWidth="1.6" />
            <path d="M10.5 6.2A10 10 0 0 1 12 6c6.5 0 10 6 10 6a18 18 0 0 1-3.2 3.8" stroke="currentColor" strokeWidth="1.6" />
            <path d="M6.1 6.8C3.8 8.5 2 12 2 12s3.5 6 10 6c1.4 0 2.7-.3 3.8-.8" stroke="currentColor" strokeWidth="1.6" />
            <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" stroke="currentColor" strokeWidth="1.6" />
        </svg>
    );
}
