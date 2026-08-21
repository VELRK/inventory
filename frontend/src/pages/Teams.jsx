import { useEffect, useState } from 'react';
import { api, getUser } from '../api';
import { Badge, Field, Modal, Pager, RowActions, confirmDelete } from '../components/ui';

const permissionOptions = [
  ['view_inventory', 'View inventory'],
  ['submit_block_requests', 'Submit block requests'],
  ['manage_users', 'Manage team users'],
];

const blank = {
  name: '', email: '', phone: '', city: '', address: '', status: 'active',
  permissions: ['view_inventory', 'submit_block_requests', 'manage_users'],
  project_ids: [],
};

export default function Teams() {
  const admin = getUser()?.role === 'promoter_admin';
  const [data, setData] = useState({ items: [], total: 0, page: 1, pages: 1, limit: 10 });
  const [limit, setLimit] = useState(10);
  const [q, setQ] = useState('');
  const [projects, setProjects] = useState([]);
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState(blank);
  const [err, setErr] = useState('');

  const load = (page = 1, lim = limit) => api(`/companies?q=${encodeURIComponent(q)}&page=${page}&limit=${lim}`).then((r) => setData(r.data)).catch((e) => setErr(e.message));
  useEffect(() => { load(); api('/projects?limit=100').then((r) => setProjects(r.data.items || [])); }, []);

  function openAdd() {
    setEditing(null);
    setForm(blank);
    setOpen(true);
  }
  function openEdit(c) {
    setEditing(c);
    setForm({
      name: c.name || '',
      email: c.email || '',
      phone: c.phone || '',
      city: c.city || '',
      address: c.address || '',
      status: c.status || 'active',
      permissions: c.permissions || [],
      project_ids: (c.project_ids || (c.projects || []).map((p) => p.id)).map(String),
    });
    setOpen(true);
  }

  async function save(e) {
    e.preventDefault();
    try {
      if (editing) await api(`/companies/${editing.id}`, { method: 'PUT', body: form });
      else await api('/companies', { method: 'POST', body: form });
      setOpen(false);
      load(editing ? data.page : 1);
    } catch (ex) { setErr(ex.message); }
  }

  async function remove(c) {
    if (!confirmDelete('company')) return;
    try {
      await api(`/companies/${c.id}`, { method: 'DELETE' });
      load(data.page);
    } catch (ex) { setErr(ex.message); }
  }

  function togglePermission(key) {
    setForm((prev) => ({
      ...prev,
      permissions: prev.permissions.includes(key)
        ? prev.permissions.filter((p) => p !== key)
        : [...prev.permissions, key],
    }));
  }

  return (
    <div>
      <div className="toolbar">
        <h1 className="page-title" style={{ marginRight: 'auto' }}>Companies</h1>
        <input className="input search" placeholder="Search company" value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && load(1)} />
        {admin && <button className="btn btn-gold" onClick={openAdd}>+ Add Company</button>}
      </div>
      {err && <div className="alert alert-err">{err}</div>}
      {data.items.map((c) => (
        <div key={c.id} className="list-card">
          <div className="avatar">{c.name.slice(0, 2).toUpperCase()}</div>
          <div>
            <strong>{c.name}</strong>
            <div className="muted">{c.email} · {c.phone || 'no phone'} · {c.city || '—'}</div>
            <div className="muted">{c.user_count} users · {(c.projects || []).map((p) => p.name).join(', ') || 'no projects'}</div>
          </div>
          <div style={{ textAlign: 'right' }}>
            <Badge status={c.status} />
            {admin && <RowActions onEdit={() => openEdit(c)} onDelete={() => remove(c)} />}
          </div>
        </div>
      ))}
      <Pager {...data} onPage={load} onLimit={(n) => { setLimit(n); load(1, n); }} />
      {open && (
        <Modal title={editing ? 'Edit company' : 'Add company'} onClose={() => setOpen(false)}>
          <form onSubmit={save} className="grid">
            <Field label="Name"><input className="input" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required /></Field>
            <Field label="Email"><input className="input" type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} required /></Field>
            <div className="grid grid-2">
              <Field label="Phone"><input className="input" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} /></Field>
              <Field label="City"><input className="input" value={form.city} onChange={(e) => setForm({ ...form, city: e.target.value })} /></Field>
            </div>
            <Field label="Address"><input className="input" value={form.address} onChange={(e) => setForm({ ...form, address: e.target.value })} /></Field>
            <Field label="Status">
              <select className="select" value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </Field>
            <div>
              <span className="label">Permissions</span>
              {permissionOptions.map(([key, label]) => (
                <label key={key} style={{ display: 'flex', gap: 8, alignItems: 'center', marginTop: 8 }}>
                  <input type="checkbox" checked={form.permissions.includes(key)} onChange={() => togglePermission(key)} />
                  {label}
                </label>
              ))}
            </div>
            <Field label="Assign projects">
              <select className="select" multiple value={form.project_ids} onChange={(e) => setForm({ ...form, project_ids: [...e.target.selectedOptions].map((o) => o.value) })}>
                {projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
              </select>
            </Field>
            <button className="btn btn-gold">{editing ? 'Update Company' : 'Save Company'}</button>
          </form>
        </Modal>
      )}
    </div>
  );
}
