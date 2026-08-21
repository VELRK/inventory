import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api, getUser } from '../api';
import { Field, Modal, Pager, RowActions, confirmDelete, ImageField } from '../components/ui';

const blank = { name: '', city: '', location: '', project_type: 'Residential Plot', approval_details: '', description: '', status: 'active', cover_image: '', cover_image_url: '' };

export default function Projects() {
  const admin = getUser()?.role === 'promoter_admin';
  const [data, setData] = useState({ items: [], total: 0, page: 1, pages: 1, limit: 10 });
  const [limit, setLimit] = useState(10);
  const [q, setQ] = useState('');
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState(blank);
  const [err, setErr] = useState('');

  const load = (page = 1, lim = limit) => api(`/projects?q=${encodeURIComponent(q)}&page=${page}&limit=${lim}`).then((r) => setData(r.data)).catch((e) => setErr(e.message));
  useEffect(() => { load(); }, []);

  function openAdd() {
    setEditing(null);
    setForm(blank);
    setOpen(true);
  }
  function openEdit(p) {
    setEditing(p);
    setForm({
      name: p.name || '',
      city: p.city || '',
      location: p.location || '',
      project_type: p.project_type || 'Residential Plot',
      approval_details: p.approval_details || '',
      description: p.description || '',
      status: p.status || 'active',
      cover_image: p.cover_image || '',
      cover_image_url: p.cover_image_url || '',
    });
    setOpen(true);
  }

  async function save(e) {
    e.preventDefault();
    try {
      if (editing) await api(`/projects/${editing.id}`, { method: 'PUT', body: form });
      else await api('/projects', { method: 'POST', body: form });
      setOpen(false);
      load(editing ? data.page : 1);
    } catch (ex) { setErr(ex.message); }
  }

  async function remove(p) {
    if (!confirmDelete('project')) return;
    try {
      await api(`/projects/${p.id}`, { method: 'DELETE' });
      load(data.page);
    } catch (ex) { setErr(ex.message); }
  }

  return (
    <div>
      <div className="toolbar">
        <h1 className="page-title" style={{ marginRight: 'auto' }}>Projects</h1>
        <input className="input search" placeholder="Search projects" value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && load(1)} />
        {admin && <button className="btn btn-gold" onClick={openAdd}>+ Add Project</button>}
      </div>
      {err && <div className="alert alert-err">{err}</div>}
      {data.items.map((p) => (
        <div key={p.id} className="list-card">
          {p.cover_image_url || p.cover_image
            ? <img className="thumb" src={p.cover_image_url || p.cover_image} alt="" />
            : <div className="thumb" />}
          <div>
            <strong>{p.name}</strong>
            <div className="muted">{p.location}, {p.city} · {p.approval_details}</div>
            <div className="unit-meta">Total {p.counts.total} · Available {p.counts.available}</div>
            <Link to={`/inventory?project_id=${p.id}`} className="muted" style={{ display: 'inline-block', marginTop: 6, color: 'var(--teal)', fontWeight: 600 }}>View units →</Link>
          </div>
          <div style={{ textAlign: 'right' }}>
            <span className="badge b-available">{p.counts.available} Available</span>
            {admin && <RowActions onEdit={() => openEdit(p)} onDelete={() => remove(p)} />}
          </div>
        </div>
      ))}
      <Pager {...data} onPage={load} onLimit={(n) => { setLimit(n); load(1, n); }} />
      {open && (
        <Modal title={editing ? 'Edit project' : 'Add project'} onClose={() => setOpen(false)}>
          <form onSubmit={save} className="grid">
            <Field label="Name"><input className="input" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></Field>
            <div className="grid grid-2">
              <Field label="City"><input className="input" value={form.city} onChange={(e) => setForm({ ...form, city: e.target.value })} /></Field>
              <Field label="Location"><input className="input" value={form.location} onChange={(e) => setForm({ ...form, location: e.target.value })} /></Field>
            </div>
            <Field label="Approval"><input className="input" value={form.approval_details} onChange={(e) => setForm({ ...form, approval_details: e.target.value })} /></Field>
            <Field label="Description"><textarea className="textarea" rows={3} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} /></Field>
            <ImageField
              label="Cover image"
              folder="projects"
              path={form.cover_image}
              url={form.cover_image_url}
              onUploaded={(d) => setForm({ ...form, cover_image: d.path, cover_image_url: d.url })}
              onClear={() => setForm({ ...form, cover_image: '', cover_image_url: '' })}
            />
            {editing && (
              <Field label="Status">
                <select className="select" value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </Field>
            )}
            <button className="btn btn-gold">{editing ? 'Update Project' : 'Save Project'}</button>
          </form>
        </Modal>
      )}
    </div>
  );
}
