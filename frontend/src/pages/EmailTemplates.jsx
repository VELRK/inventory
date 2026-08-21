import { useEffect, useState } from 'react';
import { api } from '../api';
import { Field, Modal } from '../components/ui';

export default function EmailTemplates() {
  const [items, setItems] = useState([]);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState({ name: '', subject: '', body: '', is_active: true });
  const [err, setErr] = useState('');
  const [msg, setMsg] = useState('');

  const load = () => {
    api('/email-templates')
      .then((r) => setItems(r.data.items || []))
      .catch((e) => setErr(e.message));
  };
  useEffect(() => { load(); }, []);

  function openEdit(row) {
    setEditing(row);
    setForm({
      name: row.name || '',
      subject: row.subject || '',
      body: row.body || '',
      is_active: !!row.is_active,
    });
    setErr('');
    setMsg('');
  }

  async function save(e) {
    e.preventDefault();
    try {
      await api(`/email-templates/${editing.id}`, { method: 'PUT', body: form });
      setMsg('Template saved.');
      setEditing(null);
      load();
    } catch (ex) { setErr(ex.message); }
  }

  async function restoreDefault(row) {
    if (!window.confirm(`Restore default content for "${row.name}"?`)) return;
    try {
      await api(`/email-templates/${row.id}/reset`, { method: 'POST', body: {} });
      setMsg('Restored to default.');
      load();
    } catch (ex) { setErr(ex.message); }
  }

  return (
    <div>
      <h1 className="page-title">Email templates</h1>
      <p className="muted">Manage all system emails (password reset, hold requests, bookings, and more). Placeholders like {'{name}'} are filled when sending.</p>
      {err && <div className="alert alert-err">{err}</div>}
      {msg && <div className="alert alert-ok">{msg}</div>}
      <div style={{ marginTop: 16 }}>
        {items.map((row) => (
          <div key={row.id} className="list-card">
            <div>
              <strong>{row.name}</strong>
              <div className="muted" style={{ fontSize: 13 }}>{row.event_key}</div>
              <div style={{ marginTop: 4 }}>{row.subject}</div>
              {row.placeholders && <div className="muted" style={{ fontSize: 12, marginTop: 4 }}>Placeholders: {row.placeholders}</div>}
            </div>
            <div style={{ textAlign: 'right' }}>
              <span className={`badge ${row.is_active ? 'b-available' : 'b-cancelled'}`}>{row.is_active ? 'Active' : 'Off'}</span>
              <div className="btn-row" style={{ marginTop: 8, justifyContent: 'flex-end' }}>
                <button type="button" className="btn btn-outline btn-sm" onClick={() => openEdit(row)}>Edit</button>
                <button type="button" className="btn btn-ghost btn-sm" onClick={() => restoreDefault(row)}>Reset</button>
              </div>
            </div>
          </div>
        ))}
      </div>

      {editing && (
        <Modal title={`Edit · ${editing.event_key}`} onClose={() => setEditing(null)}>
          <form onSubmit={save} className="grid">
            <Field label="Display name">
              <input className="input" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
            </Field>
            <Field label="Subject">
              <input className="input" value={form.subject} onChange={(e) => setForm({ ...form, subject: e.target.value })} required />
            </Field>
            <Field label="Body">
              <textarea className="textarea" rows={10} value={form.body} onChange={(e) => setForm({ ...form, body: e.target.value })} required />
            </Field>
            {editing.placeholders && <p className="muted" style={{ fontSize: 13 }}>Available: {editing.placeholders}</p>}
            <label style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
              <input type="checkbox" checked={form.is_active} onChange={(e) => setForm({ ...form, is_active: e.target.checked })} />
              Active (send this email)
            </label>
            <button className="btn btn-gold">Save template</button>
          </form>
        </Modal>
      )}
    </div>
  );
}
