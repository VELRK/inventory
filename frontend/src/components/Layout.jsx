import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import {
  LayoutDashboard, Building2, Boxes, Users, ClipboardList, FileBarChart,
  Activity, Settings, Database, TerminalSquare, Bell, LogOut, KeyRound, Mail, Shield
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { api, can, clearSession, getUser, mediaPreview, updateSessionUser } from '../api';
import { Field, ImageField, Modal } from './ui';

const allNav = [
  ['/', 'Dashboard', LayoutDashboard, 'nav.dashboard'],
  ['/projects', 'Projects', Building2, 'nav.projects'],
  ['/inventory', 'Inventory', Boxes, 'nav.inventory'],
  ['/teams', 'Companies', Users, 'nav.companies'],
  ['/users', 'Users', Users, 'nav.users'],
  ['/requests', 'Requests', ClipboardList, 'nav.requests'],
  ['/reports', 'Bookings', FileBarChart, 'nav.bookings'],
  ['/activity', 'Activity', Activity, 'nav.activity'],
  ['/settings', 'Settings', Settings, 'nav.settings'],
  ['/email-templates', 'Email templates', Mail, 'nav.email_templates'],
  ['/access', 'Access', Shield, 'nav.access'],
  ['/schema', 'Schema Studio', Database, 'nav.schema'],
  ['/api-tester', 'API Tester', TerminalSquare, 'nav.api_tester'],
];

export default function Layout() {
  const nav = useNavigate();
  const [user, setUser] = useState(getUser());
  const [unread, setUnread] = useState(0);
  const [photoOpen, setPhotoOpen] = useState(false);
  const [pwdOpen, setPwdOpen] = useState(false);
  const [photo, setPhoto] = useState({ avatar: '', avatar_url: '' });
  const [pwd, setPwd] = useState({ current_password: '', new_password: '', new_password_confirm: '' });
  const [err, setErr] = useState('');
  const [msg, setMsg] = useState('');
  const items = allNav.filter(([, , , perm]) => can(perm, user)).map(([to, label, Icon]) => {
    if (to === '/users' && user?.role !== 'promoter_admin') {
      return [to, 'Team Users', Icon];
    }
    if (to === '/requests' && user?.role !== 'promoter_admin') {
      return [to, 'My Requests', Icon];
    }
    return [to, label, Icon];
  });

  function applyUser(next) {
    updateSessionUser(next);
    setUser(next);
  }

  useEffect(() => {
    api('/notifications').then((r) => setUnread(r.data.unread || 0)).catch(() => {});
    api('/auth/me').then((r) => applyUser(r.data)).catch(() => {});
  }, []);

  function openPhoto() {
    setErr('');
    setPhoto({ avatar: user?.avatar || '', avatar_url: user?.avatar_url || '' });
    setPhotoOpen(true);
  }

  async function savePhoto(e) {
    e.preventDefault();
    try {
      const r = await api(`/users/${user.id}`, { method: 'PUT', body: { avatar: photo.avatar || '' } });
      applyUser(r.data);
      setPhotoOpen(false);
    } catch (ex) {
      setErr(ex.message);
    }
  }

  function openPassword() {
    setErr('');
    setMsg('');
    setPwd({ current_password: '', new_password: '', new_password_confirm: '' });
    setPwdOpen(true);
  }

  async function savePassword(e) {
    e.preventDefault();
    setErr('');
    setMsg('');
    if (pwd.new_password !== pwd.new_password_confirm) {
      setErr('Passwords do not match.');
      return;
    }
    try {
      const r = await api('/auth/change-password', { method: 'POST', body: pwd });
      setMsg(r.message || 'Password changed. Confirmation email sent.');
      setPwd({ current_password: '', new_password: '', new_password_confirm: '' });
    } catch (ex) {
      setErr(ex.message);
    }
  }

  return (
    <div className="layout">
      <aside className="sidebar">
        <div className="brand-row" style={{ padding: '0 8px 18px' }}>
          <div className="logo-mark">S</div>
          <div>
            <div className="brand-name" style={{ color: '#fff' }}>SYNCR</div>
            <div style={{ fontSize: 11, color: '#b7d4d3' }}>Inventory Portal</div>
          </div>
        </div>
        {items.map(([to, label, Icon]) => (
          <NavLink key={to} to={to} end={to === '/'} className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}>
            <Icon size={18} /> {label}
          </NavLink>
        ))}
        <div className="side-foot">
          Signed in as<br /><strong>{user?.name}</strong>
          <div style={{ marginTop: 10, display: 'grid', gap: 8 }}>
            <button type="button" className="btn btn-outline" style={{ color: '#fff', borderColor: 'rgba(255,255,255,.3)' }} onClick={openPassword}>
              <KeyRound size={14} /> Change password
            </button>
            <button type="button" className="btn btn-outline" style={{ color: '#fff', borderColor: 'rgba(255,255,255,.3)' }} onClick={() => { clearSession(); nav('/login'); }}>
              <LogOut size={14} /> Logout
            </button>
          </div>
        </div>
      </aside>
      <div className="main">
        <div className="topbar">
          <div>
            <div className="muted" style={{ fontSize: 13 }}>{user?.company_name} · {user?.role?.replaceAll('_', ' ')}</div>
          </div>
          <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
            <button className="icon-btn" type="button"><Bell size={18} />{unread > 0 && <span style={{ position: 'absolute', top: 6, right: 6, width: 8, height: 8, background: 'var(--gold)', borderRadius: 99 }} />}</button>
            <button type="button" className="avatar-btn" title="Change profile photo" onClick={openPhoto}>
              {mediaPreview(user?.avatar, user?.avatar_url)
                ? <img className="avatar" src={mediaPreview(user?.avatar, user?.avatar_url)} alt={user?.name || 'Profile'} />
                : <div className="avatar">{user?.initials}</div>}
            </button>
          </div>
        </div>
        <Outlet />
      </div>
      {photoOpen && (
        <Modal title="Profile photo" onClose={() => setPhotoOpen(false)}>
          {err && <div className="alert alert-err">{err}</div>}
          <form onSubmit={savePhoto} className="grid">
            <ImageField
              label="Your photo"
              folder="users"
              path={photo.avatar}
              url={photo.avatar_url}
              onUploaded={(d) => setPhoto({ avatar: d.path, avatar_url: d.url })}
              onClear={() => setPhoto({ avatar: '', avatar_url: '' })}
            />
            <button className="btn btn-gold">Save photo</button>
          </form>
        </Modal>
      )}
      {pwdOpen && (
        <Modal title="Change password" onClose={() => setPwdOpen(false)}>
          <p className="muted" style={{ marginTop: 0 }}>Works for admin, team admin, and team users. A confirmation email is sent after success.</p>
          {err && <div className="alert alert-err">{err}</div>}
          {msg && <div className="alert alert-ok">{msg}</div>}
          <form onSubmit={savePassword} className="grid">
            <Field label="Current password">
              <input className="input" type="password" required value={pwd.current_password} onChange={(e) => setPwd({ ...pwd, current_password: e.target.value })} autoComplete="current-password" />
            </Field>
            <Field label="New password">
              <input className="input" type="password" required minLength={6} value={pwd.new_password} onChange={(e) => setPwd({ ...pwd, new_password: e.target.value })} autoComplete="new-password" />
            </Field>
            <Field label="Confirm new password">
              <input className="input" type="password" required minLength={6} value={pwd.new_password_confirm} onChange={(e) => setPwd({ ...pwd, new_password_confirm: e.target.value })} autoComplete="new-password" />
            </Field>
            <button className="btn btn-gold">Update password</button>
          </form>
        </Modal>
      )}
    </div>
  );
}
