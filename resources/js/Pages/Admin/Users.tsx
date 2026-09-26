import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import AppShell from '@/components/Shell';
import { PageHeader } from '@/components/ui';

export default function Users({ users, roles, units }: { users: { id: number; name: string; email: string; unit: string | null; role: string; is_active: boolean }[]; roles: { id: number; label: string; name: string }[]; units: { id: number; code: string }[] }) {
    const form = useForm({ name: '', email: '', password: '', role_id: roles.find((role) => role.name === 'bendahara_unit')?.id ?? roles[0]?.id, unit_id: units[0]?.id ?? '' });
    const submit = (event: FormEvent) => { event.preventDefault(); form.post('/admin/users'); };

    return (
        <AppShell title="Pengguna">
            <PageHeader kicker="Administrasi" title="Pengguna" lede="Bendahara unit wajib terikat pada satu unit. Super admin tidak otomatis dapat menyetujui transaksi." />
            <table className="data"><thead><tr><th>Nama</th><th>Email</th><th>Peran</th><th>Unit</th><th>Aktif</th></tr></thead><tbody>{users.map((user) => <tr key={user.id}><td>{user.name}</td><td>{user.email}</td><td>{user.role}</td><td>{user.unit ?? 'Semua'}</td><td>{user.is_active ? 'Ya' : 'Tidak'}</td></tr>)}</tbody></table>
            <form onSubmit={submit} className="grid-form" style={{ marginTop: 16 }}>
                <label className="field"><span>Nama</span><input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></label>
                <label className="field"><span>Email</span><input value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} /></label>
                <label className="field"><span>Kata sandi</span><input type="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} /></label>
                <label className="field"><span>Peran</span><select value={form.data.role_id} onChange={(e) => form.setData('role_id', Number(e.target.value))}>{roles.map((role) => <option key={role.id} value={role.id}>{role.label}</option>)}</select></label>
                <label className="field"><span>Unit</span><select value={form.data.unit_id} onChange={(e) => form.setData('unit_id', Number(e.target.value))}><option value="">Tidak terikat</option>{units.map((unit) => <option key={unit.id} value={unit.id}>{unit.code}</option>)}</select></label>
                <button className="btn btn-primary">Tambah pengguna</button>
            </form>
        </AppShell>
    );
}
