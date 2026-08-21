import { Fragment, useEffect, useMemo, useState } from 'react';
import { api } from '../api';

const ROLE_ORDER = ['promoter_admin', 'marketing_team_admin', 'marketing_team_user'];

export default function AccessPage() {
  const [roles, setRoles] = useState({});
  const [rows, setRows] = useState([]);
  const [err, setErr] = useState('');
  const [msg, setMsg] = useState('');
  const [saving, setSaving] = useState(false);

  function load() {
    setErr('');
    api('/access')
      .then((r) => {
        setRoles(r.data.roles || {});
        setRows(r.data.permissions || []);
      })
      .catch((e) => setErr(e.message));
  }

  useEffect(() => { load(); }, []);

  const groups = useMemo(() => {
    const map = {};
    rows.forEach((row) => {
      if (!map[row.group]) map[row.group] = [];
      map[row.group].push(row);
    });
    return map;
  }, [rows]);

  const roleKeys = ROLE_ORDER.filter((k) => roles[k]);

  function toggle(permKey, role) {
    setRows((prev) => prev.map((row) => {
      if (row.key !== permKey) return row;
      if (role === 'promoter_admin' && (permKey === 'nav.access' || permKey === 'access.manage')) {
        return row;
      }
      return {
        ...row,
        roles: { ...row.roles, [role]: !row.roles[role] },
      };
    }));
  }

  async function save() {
    setSaving(true);
    setErr('');
    setMsg('');
    try {
      const matrix = {};
      roleKeys.forEach((role) => {
        matrix[role] = {};
        rows.forEach((row) => {
          matrix[role][row.key] = !!row.roles[role];
        });
      });
      const r = await api('/access', { method: 'PUT', body: { matrix } });
      setRoles(r.data.roles || {});
      setRows(r.data.permissions || []);
      setMsg(r.message || 'Access permissions saved. Users need to re-login or refresh to see menu changes.');
    } catch (ex) {
      setErr(ex.message);
    } finally {
      setSaving(false);
    }
  }

  return (
    <div>
      <div className="toolbar">
        <h1 className="page-title" style={{ marginRight: 'auto' }}>Access control</h1>
        <button className="btn btn-gold" type="button" disabled={saving || !rows.length} onClick={save}>
          {saving ? 'Saving…' : 'Save access'}
        </button>
      </div>
      <p className="muted" style={{ marginTop: 0 }}>
        Tick what each role can open in the menu and which actions they can perform. Changes apply after users refresh or log in again.
      </p>
      {err && <div className="alert alert-err">{err}</div>}
      {msg && <div className="alert alert-ok">{msg}</div>}

      <div className="card" style={{ overflowX: 'auto' }}>
        <table className="table">
          <thead>
            <tr>
              <th style={{ minWidth: 220 }}>Permission</th>
              {roleKeys.map((role) => (
                <th key={role} style={{ textAlign: 'center', minWidth: 120 }}>{roles[role]}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {Object.keys(groups).map((group) => (
              <Fragment key={group}>
                <tr>
                  <td colSpan={1 + roleKeys.length} style={{ fontWeight: 700, background: 'var(--bg-soft, #f5f7f7)' }}>{group}</td>
                </tr>
                {groups[group].map((row) => (
                  <tr key={row.key}>
                    <td>
                      <div>{row.label}</div>
                      <div className="muted" style={{ fontSize: 12 }}>{row.key}</div>
                    </td>
                    {roleKeys.map((role) => {
                      const locked = role === 'promoter_admin' && (row.key === 'nav.access' || row.key === 'access.manage');
                      return (
                        <td key={role} style={{ textAlign: 'center' }}>
                          <input
                            type="checkbox"
                            checked={!!row.roles[role]}
                            disabled={locked}
                            onChange={() => toggle(row.key, role)}
                            title={locked ? 'Promoter admin always keeps Access control' : row.label}
                          />
                        </td>
                      );
                    })}
                  </tr>
                ))}
              </Fragment>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
