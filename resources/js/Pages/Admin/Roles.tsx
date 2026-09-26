import { router } from '@inertiajs/react';
import { FormEvent } from 'react';
import AppShell from '@/components/Shell';
import { PageHeader } from '@/components/ui';

export default function Roles({ roles, permissions }: { roles: { id: number; label: string; description: string; permissions: string[] }[]; permissions: { name: string; label: string; group: string }[] }) {
    const save = (event: FormEvent<HTMLFormElement>, roleId: number) => {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        router.put(`/admin/roles/${roleId}`, { permissions: data.getAll('permissions') });
    };

    return (
        <AppShell title="Peran">
            <PageHeader kicker="Administrasi" title="Peran dan izin" lede="Izin diperiksa di server. Menyembunyikan menu tidak cukup." />
            {roles.map((role) => (
                <form key={role.id} onSubmit={(event) => save(event, role.id)} className="panel panel-b" style={{ marginBottom: 12 }}>
                    <h2 style={{ marginTop: 0 }}>{role.label}</h2>
                    <p className="help">{role.description}</p>
                    <div className="grid-form">
                        {permissions.map((permission) => (
                            <label key={permission.name} style={{ display: 'flex', gap: 8, fontSize: 13 }}>
                                <input type="checkbox" name="permissions" value={permission.name} defaultChecked={role.permissions.includes(permission.name)} />
                                {permission.label}
                            </label>
                        ))}
                    </div>
                    <button className="btn" style={{ marginTop: 8 }}>Simpan izin</button>
                </form>
            ))}
        </AppShell>
    );
}
