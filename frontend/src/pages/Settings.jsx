import { useEffect, useState } from 'react';
import { api } from '../api';

export default function SettingsPage() {
  const [groups, setGroups] = useState({});
  const [creds, setCreds] = useState([]);
  const [mailLog, setMailLog] = useState([]);
  const [values, setValues] = useState({});
  const [msg, setMsg] = useState('');
  const [err, setErr] = useState('');

  useEffect(() => {
    api('/settings').then((r) => {
      setGroups(r.data.groups || {});
      setMailLog(r.data.mail_log || []);
      const map = {};
      Object.values(r.data.groups || {}).flat().forEach((s) => { if (!s.is_secret) map[s.key] = s.value; });
      setValues(map);
    }).catch((e) => setErr(e.message));
    api('/settings/credentials').then((r) => setCreds(r.data)).catch(() => {});
  }, []);

  async function save(e) {
    e.preventDefault();
    try {
      await api('/settings', { method: 'POST', body: { values } });
      setMsg('Settings saved.');
    } catch (ex) { setErr(ex.message); }
  }
  async function testMail() {
    try {
      const r = await api('/settings/mail-test', { method: 'POST', body: { to: values.mail_from_email || 'admin@syncr.test' } });
      setMsg(r.message);
    } catch (ex) { setErr(ex.message); }
  }

  return (
    <div>
      <h1 className="page-title">Settings</h1>
      {err && <div className="alert alert-err">{err}</div>}
      {msg && <div className="alert alert-ok">{msg}</div>}
      <div className="card" style={{ margin: '16px 0' }}>
        <h3>Test credentials</h3>
        <table className="table">
          <thead><tr><th>Role</th><th>Email</th><th>Password</th></tr></thead>
          <tbody>
            {creds.map((c) => <tr key={c.email}><td>{c.role}</td><td>{c.email}</td><td><code>{c.password}</code></td></tr>)}
          </tbody>
        </table>
      </div>
      <form className="card" onSubmit={save}>
        <h3>Mail configuration</h3>
        <p className="muted">SMTP is used when mail_enabled is 1. Otherwise notifications are queued in mail_logs.</p>
        {['mail_enabled','mail_protocol','mail_smtp_host','mail_smtp_port','mail_smtp_user','mail_smtp_pass','mail_smtp_crypto','mail_from_email','mail_from_name'].map((k) => (
          <label key={k}><span className="label">{k}</span>
            <input className="input" type={k.includes('pass') ? 'password' : 'text'} value={values[k] || ''} onChange={(e) => setValues({ ...values, [k]: e.target.value })} />
          </label>
        ))}
        <div className="btn-row" style={{ marginTop: 14 }}>
          <button className="btn btn-gold" type="submit">Save settings</button>
          <button className="btn btn-outline" type="button" onClick={testMail}>Send test mail</button>
        </div>
      </form>
      <div className="card" style={{ marginTop: 16 }}>
        <h3>Mail log</h3>
        <table className="table">
          <thead><tr><th>To</th><th>Subject</th><th>Event</th><th>Status</th></tr></thead>
          <tbody>
            {mailLog.map((m) => <tr key={m.id}><td>{m.to_email}</td><td>{m.subject}</td><td>{m.event}</td><td>{m.status}</td></tr>)}
          </tbody>
        </table>
      </div>
    </div>
  );
}
