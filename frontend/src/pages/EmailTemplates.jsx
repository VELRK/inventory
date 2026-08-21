import { useEffect, useState } from 'react';
import { api } from '../api';
import { Field, Modal } from '../components/ui';

const emptyRecipients = {
  target_user: false,
  promoter_admin: false,
  team_admin: false,
  team_user: false,
  company_all: false,
  company_email: false,
  actor: false,
  extra_emails: '',
};

export default function EmailTemplates() {
  const [items, setItems] = useState([]);
  const [options, setOptions] = useState([]);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState({ name: '', subject: '', body: '', is_active: true, recipients: { ...emptyRecipients } });
  const [err, setErr] = useState('');
  const [msg, setMsg] = useState('');

  const load = () => {
    api('/email-templates')
      .then((r) => {
        setItems(r.data.items || []);
        setOptions(r.data.recipient_options || []);
      })
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
      recipients: { ...emptyRecipients, ...(row.recipients || {}) },
    });
    setErr('');
    setMsg('');
  }

  function toggleRecipient(key, checked) {
    const locked = editing?.locked_recipients || [];
    if (locked.includes(key) && !checked) return;
    setForm({
      ...form,
      recipients: { ...form.recipients, [key]: checked },
    });
  }

  async function save(e) {
    e.preventDefault();
    try {
      await api(`/email-templates/${editing.id}`, {
        method: 'PUT',
        body: {
          name: form.name,
          subject: form.subject,
          body: form.body,
          is_active: form.is_active,
          recipients: form.recipients,
        },
      });
      setMsg('Template saved (including who receives this mail).');
      setEditing(null);
      load();
    } catch (ex) { setErr(ex.message); }
  }

  async function restoreDefault(row) {
    if (!window.confirm(`Restore default content and recipients for "${row.name}"?`)) return;
    try {
      await api(`/email-templates/${row.id}/reset`, { method: 'POST', body: {} });
      setMsg('Restored to default.');
      load();
    } catch (ex) { setErr(ex.message); }
  }

  return (
    <div>
      <h1 className="page-title">Email templates</h1>
      <p className="muted">
        Edit subject/body and choose who receives each mail: promoter admin, company team admin/user, company contact email, target user, and more.
      </p>
      {err && <div className="alert alert-err">{err}</div>}
      {msg && <div className="alert alert-ok">{msg}</div>}
      <div style={{ marginTop: 16 }}>
        {items.map((row) => (
          <div key={row.id} className="list-card">
            <div>
              <strong>{row.name}</strong>
              <div className="muted" style={{ fontSize: 13 }}>{row.event_key}</div>
              <div style={{ marginTop: 4 }}>{row.subject}</div>
              <div className="muted" style={{ fontSize: 12, marginTop: 6 }}>
                Send to: {row.recipients_summary || '—'}
              </div>
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
              <textarea className="textarea" rows={8} value={form.body} onChange={(e) => setForm({ ...form, body: e.target.value })} required />
            </Field>
            {editing.placeholders && <p className="muted" style={{ fontSize: 13 }}>Available: {editing.placeholders}</p>}

            <div>
              <span className="label">Who receives this email</span>
              <p className="muted" style={{ margin: '4px 0 10px', fontSize: 13 }}>
                Tick every audience that should get this mail when the event fires.
              </p>
              <div className="grid" style={{ gap: 8 }}>
                {(options.length ? options : [
                  { key: 'target_user', label: 'Target user only', hint: '' },
                  { key: 'promoter_admin', label: 'Promoter admin (super admin)', hint: '' },
                  { key: 'team_admin', label: 'Company team admins', hint: '' },
                  { key: 'team_user', label: 'Company team users', hint: '' },
                  { key: 'company_all', label: 'All company users', hint: '' },
                  { key: 'company_email', label: 'Company contact email', hint: '' },
                  { key: 'actor', label: 'Action performer', hint: '' },
                ]).map((opt) => {
                  const locked = (editing.locked_recipients || []).includes(opt.key);
                  return (
                    <label key={opt.key} style={{ display: 'flex', gap: 10, alignItems: 'flex-start' }}>
                      <input
                        type="checkbox"
                        checked={!!form.recipients[opt.key]}
                        disabled={locked}
                        onChange={(e) => toggleRecipient(opt.key, e.target.checked)}
                        style={{ marginTop: 3 }}
                      />
                      <span>
                        <strong>{opt.label}</strong>
                        {locked && <span className="muted"> (required)</span>}
                        {opt.hint && <div className="muted" style={{ fontSize: 12 }}>{opt.hint}</div>}
                      </span>
                    </label>
                  );
                })}
              </div>
              <Field label="Extra emails (optional, comma-separated)">
                <input
                  className="input"
                  placeholder="ops@example.com, manager@example.com"
                  value={form.recipients.extra_emails || ''}
                  onChange={(e) => setForm({
                    ...form,
                    recipients: { ...form.recipients, extra_emails: e.target.value },
                  })}
                />
              </Field>
            </div>

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
