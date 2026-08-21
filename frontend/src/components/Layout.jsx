import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import {
  LayoutDashboard, Building2, Boxes, Users, ClipboardList, FileBarChart,
  Activity, Settings, Database, TerminalSquare, Bell, LogOut
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { api, clearSession, getUser, updateSessionUser } from '../api';
import { ImageField, Modal } from './ui';

const adminNav = [
  ['/', 'Dashboard', LayoutDashboard],
  ['/projects', 'Projects', Building2],
  ['/inventory', 'Inventory', Boxes],
  ['/teams', 'Companies', Users],
  ['/users', 'Users', Users],
  ['/requests', 'Requests', ClipboardList],
  ['/reports', 'Bookings', FileBarChart],
  ['/activity', 'Activity', Activity],
  ['/settings', 'Settings', Settings],
  ['/schema', 'Schema Studio', Database],
  ['/api-tester', 'API Tester', TerminalSquare],
];

const teamNav = [
  ['/', 'Dashboard', LayoutDashboard],
  ['/projects', 'Projects', Building2],
  ['/inventory', 'Inventory', Boxes],
  ['/requests', 'My Requests', ClipboardList],
  ['/users', 'Team Users', Users],
];

export default function Layout() {
  const nav = useNavigate();
  const [user, setUser] = useState(getUser());
  const [unread, setUnread] = useState(0);
  const [photoOpen, setPhotoOpen] = useState(false);
  const [photo, setPhoto] = useState({ avatar: '', avatar_url: '' });
  const [err, setErr] = useState('');
  const items = user?.role === 'promoter_admin' ? adminNav : teamNav;

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
          <div style={{ marginTop: 10 }}>
            <button className="btn btn-outline" style={{ color: '#fff', borderColor: 'rgba(255,255,255,.3)' }} onClick={() => { clearSession(); nav('/login'); }}>
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
              {user?.avatar_url
                ? <img className="avatar" src={user.avatar_url} alt={user?.name || 'Profile'} />
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
    </div>
  );
}
