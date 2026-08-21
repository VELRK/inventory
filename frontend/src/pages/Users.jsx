import { useEffect, useState } from 'react';
import { api, getUser, updateSessionUser } from '../api';
import { Badge, Field, Modal, Pager, RowActions, confirmDelete, ImageField } from '../components/ui';

export default function UsersPage() {
  const me = getUser();
  const canManage = me?.role !== 'marketing_team_user';
  const admin = me?.role === 'promoter_admin';
  const [data, setData] = useState({ items: [], total: 0, page: 1, pages: 1, limit: 10 });
  const [limit, setLimit] = useState(10);
  const [companies, setCompanies] = useState([]);
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState({ name: '', email: '', password: '', phone: '', role: 'marketing_team_user', company_id: me?.company_id || '', status: 'active', avatar: '', avatar_url: '' });
  const [err, setErr] = useState('');
  const [msg, setMsg] = useState('');

  const load = (page = 1, lim = limit) => api(`/users?page=${page}&limit=${lim}`).then((r) => setData(r.data)).catch((e) => setErr(e.message));
  useEffect(() => {
    load();
    if (admin) api('/companies?limit=100').then((r) => setCompanies(r.data.items || [])).catch(() => {});
  }, []);

  function openAdd() {
    setEditing(null);
    setForm({ name: '', email: '', password: '', phone: '', role: 'marketing_team_user', company_id: me?.company_id || '', status: 'active', avatar: '', avatar_url: '' });
    setErr('');
    setMsg('');
    setOpen(true);
  }
  function openEdit(u) {
    setEditing(u);
    setForm({
      name: u.name || '',
      email: u.email || '',
      password: '',
      phone: u.phone || '',
      role: u.role,
      company_id: u.company_id || '',
      status: u.status || 'active',
      avatar: u.avatar || '',
      avatar_url: u.avatar_url || '',
    });
    setErr('');
    setMsg('');
    setOpen(true);
  }

  async function save(e) {
    e.preventDefault();
    setErr('');
    setMsg('');
    try {
      const body = { ...form };
      if (!body.password) delete body.password;
      if (!admin) body.company_id = me.company_id;
      if (editing) {
        const r = await api(`/users/${editing.id}`, { method: 'PUT', body });
        if (editing.id === me.id) updateSessionUser(r.data);
        setOpen(false);
        load(data.page);
      } else {
        delete body.password;
        const r = await api('/users', { method: 'POST', body });
        setOpen(false);
        setMsg(r.message || 'User created. Set-password link emailed.');
        load(1);
      }
    } catch (ex) { setErr(ex.message); }
  }

  async function remove(u) {
    if (u.id === me.id) {
      setErr('You cannot delete your own account.');
      return;
    }
    if (!confirmDelete('user')) return;
    try {
      await api(`/users/${u.id}`, { method: 'DELETE' });
      load(data.page);
    } catch (ex) { setErr(ex.message); }
  }

  return (
    <div>
      <div className="toolbar">
        <h1 className="page-title" style={{ marginRight: 'auto' }}>Team users</h1>
        {canManage && <button className="btn btn-gold" onClick={openAdd}>+ Add User</button>}
      </div>
      {err && <div className="alert alert-err">{err}</div>}
      {msg && <div className="alert alert-ok">{msg}</div>}
      {data.items.map((u) => (
        <div key={u.id} className="list-card">
          {u.avatar_url
            ? <img className="avatar" src={u.avatar_url} alt="" />
            : <div className="avatar">{u.initials}</div>}
          <div>
            <strong>{u.name}</strong>
            <div className="muted">{u.email} · {u.role.replaceAll('_', ' ')}</div>
            <div className="muted">{u.company_name}{u.phone ? ` · ${u.phone}` : ''}</div>
          </div>
          <div style={{ textAlign: 'right' }}>
            <Badge status={u.status} />
            {canManage && <RowActions onEdit={() => openEdit(u)} onDelete={u.id !== me.id ? () => remove(u) : undefined} />}
          </div>
        </div>
      ))}
      <Pager {...data} onPage={load} onLimit={(n) => { setLimit(n); load(1, n); }} />
      {open && (
        <Modal title={editing ? 'Edit user' : 'Add user'} onClose={() => setOpen(false)}>
          <form onSubmit={save} className="grid">
            <Field label="Name"><input className="input" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></Field>
            <Field label="Email"><input className="input" type="email" required value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} disabled={!!editing} /></Field>
            {editing ? (
              <Field label="New password (optional)">
                <input className="input" type="password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} autoComplete="new-password" />
              </Field>
            ) : (
              <p className="muted" style={{ margin: 0, gridColumn: '1 / -1' }}>
                No password here — the user gets an email link to set their own password (valid 48 hours).
              </p>
            )}
            <Field label="Phone"><input className="input" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} /></Field>
            <ImageField
              label="Profile photo"
              folder="users"
              path={form.avatar}
              url={form.avatar_url}
              onUploaded={(d) => setForm({ ...form, avatar: d.path, avatar_url: d.url })}
              onClear={() => setForm({ ...form, avatar: '', avatar_url: '' })}
            />
            {admin && (
              <>
                <Field label="Company">
                  <select className="select" value={form.company_id} onChange={(e) => setForm({ ...form, company_id: e.target.value })}>
                    <option value="">Promoter (no company)</option>
                    {companies.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                  </select>
                </Field>
                <Field label="Role">
                  <select className="select" value={form.role} onChange={(e) => setForm({ ...form, role: e.target.value })}>
                    <option value="marketing_team_user">Team user</option>
                    <option value="marketing_team_admin">Team admin</option>
                    <option value="promoter_admin">Promoter admin</option>
                  </select>
                </Field>
              </>
            )}
            {editing && (
              <Field label="Status">
                <select className="select" value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </Field>
            )}
            <button className="btn btn-gold">{editing ? 'Update User' : 'Save User'}</button>
          </form>
        </Modal>
      )}
    </div>
  );
}
