import { useMemo, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { api } from '../api';

function readToken(params) {
  let token = params.get('token') || '';
  if (!token && typeof window !== 'undefined') {
    // Some mail clients break query strings; also try hash ?token=
    const hash = window.location.hash || '';
    const m = hash.match(/token=([^&]+)/i);
    if (m) token = decodeURIComponent(m[1]);
  }
  try {
    token = decodeURIComponent(token.trim());
  } catch {
    token = token.trim();
  }
  return token;
}

export default function ResetPassword() {
  const [params] = useSearchParams();
  const nav = useNavigate();
  const token = useMemo(() => readToken(params), [params]);
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [err, setErr] = useState('');
  const [msg, setMsg] = useState('');
  const [loading, setLoading] = useState(false);

  async function submit(e) {
    e.preventDefault();
    setErr('');
    setMsg('');
    if (!token) {
      setErr('Password link is missing or invalid. Request a new one from Forgot password.');
      return;
    }
    if (password.length < 6) {
      setErr('Password must be at least 6 characters.');
      return;
    }
    if (password !== confirm) {
      setErr('Passwords do not match.');
      return;
    }
    setLoading(true);
    try {
      const res = await api('/auth/reset', {
        method: 'POST',
        body: { token, password, password_confirm: confirm },
      });
      setMsg(res.message || 'Password saved. You can sign in now.');
      setTimeout(() => nav('/login'), 1600);
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
        <h1>Set your password</h1>
        <p className="muted">Same link is used for new users and password reset. Choose a password, then sign in.</p>
        {!token && <div className="alert alert-err">This page needs a valid link from your email.</div>}
        {err && <div className="alert alert-err">{err}</div>}
        {msg && <div className="alert alert-ok">{msg}</div>}
        <span className="label">New password</span>
        <input
          className="input"
          type="password"
          required
          minLength={6}
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          autoComplete="new-password"
        />
        <span className="label">Confirm password</span>
        <input
          className="input"
          type="password"
          required
          minLength={6}
          value={confirm}
          onChange={(e) => setConfirm(e.target.value)}
          autoComplete="new-password"
        />
        <div style={{ margin: '18px 0 10px' }}>
          <button className="btn btn-gold btn-block" disabled={loading || !token}>
            {loading ? 'Saving…' : 'Save password'}
          </button>
        </div>
        <p className="muted" style={{ fontSize: 13, textAlign: 'center' }}>
          <Link to="/forgot" style={{ color: 'var(--teal)', fontWeight: 600 }}>Request new link</Link>
          {' · '}
          <Link to="/login" style={{ color: 'var(--teal)', fontWeight: 600 }}>Login</Link>
        </p>
      </form>
    </div>
  );
}
