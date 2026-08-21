import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api';

const SMTP_FIELDS = [
  ['mail_smtp_host', 'SMTP Host', 'smtp.hostinger.com'],
  ['mail_smtp_port', 'SMTP Port', '465'],
  ['mail_from_name', 'From Name', 'Inventory'],
  ['mail_smtp_user', 'SMTP Username', 'info@superfinelabels.in'],
  ['mail_smtp_pass', 'SMTP Password', ''],
  ['mail_from_email', 'From Email', 'info@superfinelabels.in'],
  ['mail_smtp_crypto', 'Encryption', 'ssl'],
  ['mail_enabled', 'Mail enabled (1/0)', '1'],
  ['app_frontend_url', 'App URL (reset links)', 'https://superfinelabels.in/plots/app'],
];

export default function SettingsPage() {
  const [creds, setCreds] = useState([]);
  const [mailLog, setMailLog] = useState([]);
  const [values, setValues] = useState({});
  const [msg, setMsg] = useState('');
  const [err, setErr] = useState('');

  useEffect(() => {
    api('/settings').then((r) => {
      setMailLog(r.data.mail_log || []);
      const map = {};
      Object.values(r.data.groups || {}).flat().forEach((s) => {
        if (s.is_secret) map[s.key] = '';
        else map[s.key] = s.value;
      });
      // sensible Hostinger defaults if empty
      if (!map.mail_smtp_host) map.mail_smtp_host = 'smtp.hostinger.com';
      if (!map.mail_smtp_port) map.mail_smtp_port = '465';
      if (!map.mail_smtp_crypto) map.mail_smtp_crypto = 'ssl';
      if (!map.mail_from_name) map.mail_from_name = 'Inventory';
      if (!map.mail_from_email) map.mail_from_email = 'info@superfinelabels.in';
      if (!map.mail_smtp_user) map.mail_smtp_user = 'info@superfinelabels.in';
      if (!map.app_frontend_url) map.app_frontend_url = 'https://superfinelabels.in/plots/app';
      setValues(map);
    }).catch((e) => setErr(e.message));
    api('/settings/credentials').then((r) => setCreds(r.data)).catch(() => {});
  }, []);

  async function save(e) {
    e.preventDefault();
    setErr('');
    try {
      await api('/settings', { method: 'POST', body: { values } });
      setMsg('SMTP settings saved.');
    } catch (ex) { setErr(ex.message); }
  }
  async function testMail() {
    setErr('');
    try {
      const r = await api('/settings/mail-test', { method: 'POST', body: {} });
      setMsg(r.message || 'Test mail sent to your login email.');
      const s = await api('/settings');
      setMailLog(s.data.mail_log || []);
    } catch (ex) { setErr(ex.message); }
  }

  return (
    <div>
      <div className="toolbar">
        <h1 className="page-title" style={{ marginRight: 'auto' }}>Settings</h1>
        <Link className="btn btn-outline" to="/email-templates">Email templates</Link>
      </div>
      {err && <div className="alert alert-err">{err}</div>}
      {msg && <div className="alert alert-ok">{msg}</div>}

      <form className="card" onSubmit={save} style={{ marginTop: 16 }}>
        <h3>SMTP configuration</h3>
        <p className="muted">Hostinger SMTP for password reset and all portal emails. Leave password blank to keep the current secret.</p>
        <div className="grid grid-3" style={{ marginTop: 12 }}>
          {SMTP_FIELDS.slice(0, 3).map(([k, label, ph]) => (
            <label key={k}>
              <span className="label">{label}</span>
              <input className="input" value={values[k] || ''} placeholder={ph} onChange={(e) => setValues({ ...values, [k]: e.target.value })} />
            </label>
          ))}
        </div>
        <div className="grid grid-2" style={{ marginTop: 8 }}>
          {SMTP_FIELDS.slice(3, 5).map(([k, label, ph]) => (
            <label key={k}>
              <span className="label">{label}</span>
              <input
                className="input"
                type={k.includes('pass') ? 'password' : 'text'}
                value={values[k] || ''}
                placeholder={k.includes('pass') ? '••••••••' : ph}
                onChange={(e) => setValues({ ...values, [k]: e.target.value })}
                autoComplete="off"
              />
            </label>
          ))}
        </div>
        <div className="grid grid-2" style={{ marginTop: 8 }}>
          {SMTP_FIELDS.slice(5).map(([k, label, ph]) => (
            <label key={k}>
              <span className="label">{label}</span>
              <input className="input" value={values[k] || ''} placeholder={ph} onChange={(e) => setValues({ ...values, [k]: e.target.value })} />
            </label>
          ))}
        </div>
        <div className="btn-row" style={{ marginTop: 14 }}>
          <button className="btn btn-gold" type="submit">Save SMTP</button>
          <button className="btn btn-outline" type="button" onClick={testMail}>Send test mail to my email</button>
        </div>
      </form>

      <div className="card" style={{ margin: '16px 0' }}>
        <h3>Test credentials</h3>
        <table className="table">
          <thead><tr><th>Role</th><th>Email</th><th>Password</th></tr></thead>
          <tbody>
            {creds.map((c) => <tr key={c.email}><td>{c.role}</td><td>{c.email}</td><td><code>{c.password}</code></td></tr>)}
          </tbody>
        </table>
      </div>

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
