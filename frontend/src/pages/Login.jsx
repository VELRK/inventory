import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { api, setSession } from '../api';

const demos = [
  ['Admin', 'admin@syncr.test', 'Admin@123'],
  ['Team Admin', 'teamadmin@abc.test', 'TeamAdmin@123'],
  ['Team User', 'user@abc.test', 'TeamUser@123'],
];

export default function Login() {
  const nav = useNavigate();
  const [email, setEmail] = useState('admin@syncr.test');
  const [password, setPassword] = useState('Admin@123');
  const [err, setErr] = useState('');
  const [loading, setLoading] = useState(false);

  async function submit(e) {
    e.preventDefault();
    setErr('');
    setLoading(true);
    try {
      const res = await api('/auth/login', { method: 'POST', body: { email, password } });
      setSession(res.data.token, res.data.user);
      nav('/');
    } catch (ex) {
      setErr(ex.message);
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="auth-shell">
      <form className="auth-card" onSubmit={submit}>
        <div className="brand-row">
          <div className="logo-mark">S</div>
          <div className="brand-name">SYNCR</div>
        </div>
        <h1>Welcome Back!</h1>
        <p className="muted">Sign in to the real estate inventory portal.</p>
        {err && <div className="alert alert-err">{err}</div>}
        <span className="label">Email</span>
        <input className="input" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="you@company.com" autoComplete="username" />
        <span className="label">Password</span>
        <input className="input" type="password" value={password} onChange={(e) => setPassword(e.target.value)} autoComplete="current-password" />
        <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 8 }}>
          <Link to="/forgot" style={{ fontSize: 13, color: 'var(--teal)', fontWeight: 600 }}>Forgot password?</Link>
        </div>
        <div style={{ margin: '18px 0 10px' }}>
          <button className="btn btn-gold btn-block" disabled={loading}>{loading ? 'Signing in…' : 'Login'}</button>
        </div>
        <p className="muted" style={{ fontSize: 13 }}>Demo accounts (also stored in Settings)</p>
        <div className="chips">
          {demos.map(([role, em, pw]) => (
            <button type="button" key={em} className="chip" onClick={() => { setEmail(em); setPassword(pw); }}>{role}</button>
          ))}
        </div>
      </form>
    </div>
  );
}
