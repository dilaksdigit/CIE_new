// SOURCE: CIE_v232_UI_Restructure_Instructions.docx §2.4 — Admin user management
import React, { useContext, useEffect, useState } from 'react';
import THEME from '../theme';
import { AppContext } from '../App';
import { canManageUsers, ROLES } from '../lib/rbac';
import { usersApi } from '../services/api';

const ROLE_OPTIONS = Object.keys(ROLES).map((name) => ({
    label: name.replace(/_/g, ' '),
    value: name,
}));

const AdminUsers = () => {
    const { user, addNotification } = useContext(AppContext);
    const isAdmin = canManageUsers(user);

    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [modalOpen, setModalOpen] = useState(false);
    const [form, setForm] = useState({
        email: '',
        password: '',
        first_name: '',
        last_name: '',
        role: 'CONTENT_EDITOR',
    });
    const [saving, setSaving] = useState(false);

    const loadUsers = async () => {
        setLoading(true);
        try {
            const res = await usersApi.list();
            const list = res.data?.data ?? res.data ?? [];
            setUsers(Array.isArray(list) ? list : []);
        } catch (e) {
            addNotification({ type: 'error', message: 'Failed to load users.' });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (isAdmin) loadUsers();
        else setLoading(false);
    }, [isAdmin]);

    const handleCreate = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            await usersApi.create({
                email: form.email.trim(),
                password: form.password,
                first_name: form.first_name.trim(),
                last_name: form.last_name.trim(),
                role: form.role,
            });
            addNotification({ type: 'success', message: 'User created.' });
            setModalOpen(false);
            setForm({ email: '', password: '', first_name: '', last_name: '', role: 'CONTENT_EDITOR' });
            loadUsers();
        } catch (err) {
            const msg = err.response?.data?.message || err.response?.data?.error || 'Failed to create user.';
            addNotification({ type: 'error', message: msg });
        } finally {
            setSaving(false);
        }
    };

    const handleDeactivate = async (row) => {
        if (!window.confirm(`Deactivate ${row.email}?`)) return;
        try {
            await usersApi.update(row.id, { is_active: false });
            addNotification({ type: 'success', message: 'User deactivated.' });
            loadUsers();
        } catch (err) {
            addNotification({ type: 'error', message: 'Failed to deactivate user.' });
        }
    };

    if (!isAdmin) {
        return (
            <div className="page-container" style={{ padding: 24 }}>
                <p style={{ color: THEME.textMid }}>You do not have permission to manage users.</p>
            </div>
        );
    }

    return (
        <div className="page-container" style={{ padding: 24 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 }}>
                <div>
                    <h1 style={{ color: THEME.text, margin: 0 }}>Users</h1>
                    <p style={{ color: THEME.textMid, marginTop: 8 }}>Create and manage CIE accounts. Self-registration is disabled.</p>
                </div>
                <button
                    type="button"
                    className="btn-primary"
                    onClick={() => setModalOpen(true)}
                    style={{ background: THEME.accent, color: '#fff', border: 'none', padding: '8px 16px', cursor: 'pointer' }}
                >
                    Create User
                </button>
            </div>

            {loading ? (
                <p style={{ color: THEME.textMid }}>Loading users…</p>
            ) : (
                <table className="data-table" style={{ width: '100%', borderCollapse: 'collapse' }}>
                    <thead>
                        <tr style={{ background: '#1F2D54', color: '#fff' }}>
                            <th style={{ padding: 10, textAlign: 'left' }}>Email</th>
                            <th style={{ padding: 10, textAlign: 'left' }}>Name</th>
                            <th style={{ padding: 10, textAlign: 'left' }}>Roles</th>
                            <th style={{ padding: 10, textAlign: 'left' }}>Active</th>
                            <th style={{ padding: 10, textAlign: 'left' }}>Created</th>
                            <th style={{ padding: 10, textAlign: 'left' }}>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {users.map((row, idx) => (
                            <tr key={row.id} style={{ background: idx % 2 ? THEME.muted : THEME.surface }}>
                                <td style={{ padding: 10, borderBottom: `1px solid ${THEME.border}` }}>{row.email}</td>
                                <td style={{ padding: 10, borderBottom: `1px solid ${THEME.border}` }}>
                                    {[row.first_name, row.last_name].filter(Boolean).join(' ')}
                                </td>
                                <td style={{ padding: 10, borderBottom: `1px solid ${THEME.border}` }}>
                                    {(row.roles || []).join(', ')}
                                </td>
                                <td style={{ padding: 10, borderBottom: `1px solid ${THEME.border}` }}>
                                    {row.is_active ? 'Yes' : 'No'}
                                </td>
                                <td style={{ padding: 10, borderBottom: `1px solid ${THEME.border}` }}>{row.created_at || '—'}</td>
                                <td style={{ padding: 10, borderBottom: `1px solid ${THEME.border}` }}>
                                    {row.is_active && (
                                        <button
                                            type="button"
                                            onClick={() => handleDeactivate(row)}
                                            style={{ color: '#C62828', background: 'transparent', border: '1px solid #EF9A9A', padding: '4px 10px', cursor: 'pointer' }}
                                        >
                                            Deactivate
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}

            {modalOpen && (
                <div
                    role="dialog"
                    style={{
                        position: 'fixed',
                        inset: 0,
                        background: 'rgba(0,0,0,0.35)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        zIndex: 1000,
                    }}
                    onClick={() => setModalOpen(false)}
                >
                    <form
                        onSubmit={handleCreate}
                        onClick={(e) => e.stopPropagation()}
                        style={{ background: THEME.surface, padding: 24, minWidth: 400, border: `1px solid ${THEME.border}` }}
                    >
                        <h2 style={{ marginTop: 0, color: THEME.text }}>Create User</h2>
                        <label style={{ display: 'block', marginBottom: 12 }}>
                            <span style={{ color: THEME.textMid, fontSize: '0.85rem' }}>Email</span>
                            <input
                                type="email"
                                required
                                value={form.email}
                                onChange={(e) => setForm({ ...form, email: e.target.value })}
                                style={{ display: 'block', width: '100%', marginTop: 4, padding: 8 }}
                            />
                        </label>
                        <label style={{ display: 'block', marginBottom: 12 }}>
                            <span style={{ color: THEME.textMid, fontSize: '0.85rem' }}>Password (min 8)</span>
                            <input
                                type="password"
                                required
                                minLength={8}
                                value={form.password}
                                onChange={(e) => setForm({ ...form, password: e.target.value })}
                                style={{ display: 'block', width: '100%', marginTop: 4, padding: 8 }}
                            />
                        </label>
                        <label style={{ display: 'block', marginBottom: 12 }}>
                            <span style={{ color: THEME.textMid, fontSize: '0.85rem' }}>First name</span>
                            <input
                                type="text"
                                required
                                value={form.first_name}
                                onChange={(e) => setForm({ ...form, first_name: e.target.value })}
                                style={{ display: 'block', width: '100%', marginTop: 4, padding: 8 }}
                            />
                        </label>
                        <label style={{ display: 'block', marginBottom: 12 }}>
                            <span style={{ color: THEME.textMid, fontSize: '0.85rem' }}>Last name</span>
                            <input
                                type="text"
                                value={form.last_name}
                                onChange={(e) => setForm({ ...form, last_name: e.target.value })}
                                style={{ display: 'block', width: '100%', marginTop: 4, padding: 8 }}
                            />
                        </label>
                        <label style={{ display: 'block', marginBottom: 16 }}>
                            <span style={{ color: THEME.textMid, fontSize: '0.85rem' }}>Role</span>
                            <select
                                value={form.role}
                                onChange={(e) => setForm({ ...form, role: e.target.value })}
                                style={{ display: 'block', width: '100%', marginTop: 4, padding: 8 }}
                            >
                                {ROLE_OPTIONS.map((opt) => (
                                    <option key={opt.value} value={opt.value}>{opt.label}</option>
                                ))}
                            </select>
                        </label>
                        <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                            <button type="button" onClick={() => setModalOpen(false)}>Cancel</button>
                            <button type="submit" disabled={saving} style={{ background: THEME.accent, color: '#fff', border: 'none', padding: '8px 16px' }}>
                                {saving ? 'Saving…' : 'Create'}
                            </button>
                        </div>
                    </form>
                </div>
            )}
        </div>
    );
};

export default AdminUsers;
