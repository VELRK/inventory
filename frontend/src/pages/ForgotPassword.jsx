import { useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api';

export default function ForgotPassword() {
  const [email, setEmail] = useState('');
  const [err, setErr] = useState('');
  const [msg, setMsg] = useState('');
  const [loading, setLoading] = useState(false);

  async function submit(e) {
    e.preventDefault();
    setErr('');
    setMsg('');
    setLoading(true);
    try {
      const res = await api('/auth/forgot', { method: 'POST', body: { email } });
      setMsg(res.message || 'If that email is registered, a reset link has been sent.');
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
        <h1>Forgot password</h1>
        <p className="muted">Enter your email. We will send a reset confirmation link for admin, team admin, or team user accounts.</p>
        {err && <div className="alert alert-err">{err}</div>}
        {msg && <div className="alert alert-ok">{msg}</div>}
        <span className="label">Email</span>
        <input
          className="input"
          type="email"
          required
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          placeholder="you@company.com"
          autoComplete="email"
        />
        <div style={{ margin: '18px 0 10px' }}>
          <button className="btn btn-gold btn-block" disabled={loading}>
            {loading ? 'Sending…' : 'Send reset link'}
          </button>
        </div>
        <p className="muted" style={{ fontSize: 13, textAlign: 'center' }}>
          <Link to="/login" style={{ color: 'var(--teal)', fontWeight: 600 }}>Back to login</Link>
        </p>
      </form>
    </div>
  );
}
