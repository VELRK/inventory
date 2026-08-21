import { useEffect, useState } from 'react';
import { api } from '../api';
import { Field } from '../components/ui';

export default function SchemaStudio() {
  const [tables, setTables] = useState([]);
  const [table, setTable] = useState('projects');
  const [cols, setCols] = useState([]);
  const [sql, setSql] = useState('SELECT * FROM projects LIMIT 20');
  const [rows, setRows] = useState(null);
  const [selected, setSelected] = useState([]);
  const [form, setForm] = useState({ column: '', type: 'VARCHAR', length: '100', nullable: true, default: '' });
  const [err, setErr] = useState('');
  const [msg, setMsg] = useState('');
  const [logs, setLogs] = useState([]);

  const refresh = () => {
    api('/schema/tables').then((r) => setTables(r.data));
    api(`/schema/columns?table=${table}`).then((r) => setCols(r.data));
    api('/schema/logs').then((r) => setLogs(r.data));
  };
  useEffect(() => { refresh(); setSelected([]); setRows(null); }, [table]);

  async function runQuery(e) {
    e?.preventDefault();
    setErr(''); setMsg('');
    try {
      const r = await api('/schema/query', { method: 'POST', body: { sql } });
      setRows(r.data.rows || []);
      setSelected([]);
      if (r.data.affected != null) setMsg(`${r.data.affected} row(s) deleted`);
      else setMsg(`${r.data.count} rows`);
      refresh();
    } catch (ex) { setErr(ex.message); setRows(null); }
  }

  async function loadTableData() {
    setSql(`SELECT * FROM \`${table}\` LIMIT 50`);
    setErr(''); setMsg('');
    try {
      const r = await api('/schema/query', { method: 'POST', body: { sql: `SELECT * FROM \`${table}\` LIMIT 50` } });
      setRows(r.data.rows || []);
      setSelected([]);
      setMsg(`${r.data.count} rows from ${table}`);
    } catch (ex) { setErr(ex.message); setRows(null); }
  }

  async function deleteSelected() {
    if (!selected.length) return;
    if (!window.confirm(`Delete ${selected.length} selected row(s) from ${table}? This is logged in Activity.`)) return;
    setErr('');
    try {
      const r = await api('/schema/delete-data', { method: 'POST', body: { table, ids: selected } });
      setMsg(r.message || `${r.data.affected} deleted`);
      setSelected([]);
      loadTableData();
      refresh();
    } catch (ex) { setErr(ex.message); }
  }

  async function clearTableData() {
    if (!window.confirm(`Delete ALL rows from ${table}? Structure is kept. This is logged in Activity.`)) return;
    setErr('');
    try {
      const r = await api('/schema/delete-data', { method: 'POST', body: { table, confirm: true } });
      setMsg(r.message || `${r.data.affected} deleted`);
      setSelected([]);
      setRows([]);
      refresh();
    } catch (ex) { setErr(ex.message); }
  }

  async function addColumn(e) {
    e.preventDefault();
    setErr('');
    try {
      const r = await api('/schema/add-column', { method: 'POST', body: { table, ...form } });
      setMsg(r.data.sql);
      refresh();
    } catch (ex) { setErr(ex.message); }
  }

  const hasId = rows && rows[0] && Object.prototype.hasOwnProperty.call(rows[0], 'id');
  const toggle = (id) => {
    setSelected((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
  };
  const toggleAll = () => {
    if (!hasId) return;
    const ids = rows.map((r) => r.id);
    setSelected(selected.length === ids.length ? [] : ids);
  };

  return (
    <div>
      <h1 className="page-title">Schema Studio</h1>
      <p className="muted">Select a table to view or delete its data. DROP / TRUNCATE stay blocked; DELETE is logged in Activity.</p>
      {err && <div className="alert alert-err">{err}</div>}
      {msg && <div className="alert alert-ok">{msg}</div>}
      <div className="grid grid-2" style={{ marginTop: 16 }}>
        <div className="card">
          <Field label="Table">
            <select className="select" value={table} onChange={(e) => setTable(e.target.value)}>
              {tables.map((t) => <option key={t.name} value={t.name}>{t.name} ({t.row_estimate || 0})</option>)}
            </select>
          </Field>
          <div className="btn-row" style={{ marginBottom: 12 }}>
            <button type="button" className="btn btn-gold" onClick={loadTableData}>Select table data</button>
            <button type="button" className="btn btn-outline" onClick={deleteSelected} disabled={!selected.length}>Delete selected</button>
            <button type="button" className="btn btn-danger" onClick={clearTableData}>Delete all table data</button>
          </div>
          <table className="table">
            <thead><tr><th>Column</th><th>Type</th><th>Null</th></tr></thead>
            <tbody>{cols.map((c) => <tr key={c.name}><td>{c.name}</td><td>{c.type}</td><td>{c.nullable ? 'YES' : 'NO'}</td></tr>)}</tbody>
          </table>
          <h3>Add column</h3>
          <form onSubmit={addColumn} className="grid">
            <Field label="Name"><input className="input" value={form.column} onChange={(e) => setForm({ ...form, column: e.target.value })} /></Field>
            <div className="grid grid-2">
              <Field label="Type">
                <select className="select" value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })}>
                  {['VARCHAR','INT','TEXT','DECIMAL','DATE','DATETIME','TINYINT'].map((t) => <option key={t}>{t}</option>)}
                </select>
              </Field>
              <Field label="Length"><input className="input" value={form.length} onChange={(e) => setForm({ ...form, length: e.target.value })} /></Field>
            </div>
            <button className="btn btn-gold">ALTER ADD COLUMN</button>
          </form>
        </div>
        <div className="card">
          <h3>Query / DELETE</h3>
          <textarea className="textarea" rows={6} value={sql} onChange={(e) => setSql(e.target.value)} />
          <div className="btn-row" style={{ margin: '10px 0' }}>
            <button className="btn btn-gold" onClick={runQuery}>Run</button>
            <button className="btn btn-outline" onClick={() => setSql(`DELETE FROM \`${table}\` WHERE id = 0`)}>Sample DELETE</button>
            <button className="btn btn-outline" onClick={() => setSql('DROP TABLE users')}>Try DROP (blocked)</button>
          </div>
          {rows && (
            <div style={{ overflow: 'auto' }}>
              <table className="table">
                <thead>
                  <tr>
                    {hasId && <th><input type="checkbox" checked={selected.length > 0 && selected.length === rows.length} onChange={toggleAll} /></th>}
                    {Object.keys(rows[0] || {}).map((k) => <th key={k}>{k}</th>)}
                  </tr>
                </thead>
                <tbody>
                  {rows.map((r, i) => (
                    <tr key={r.id ?? i}>
                      {hasId && (
                        <td>
                          <input type="checkbox" checked={selected.includes(r.id)} onChange={() => toggle(r.id)} />
                        </td>
                      )}
                      {Object.values(r).map((v, j) => <td key={j}>{String(v)}</td>)}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
      <div className="card" style={{ marginTop: 16 }}>
        <h3>Change log</h3>
        {logs.map((l) => <div key={l.id} className="muted">{l.created_at} · {l.status} · {l.operation} · {l.message}</div>)}
      </div>
    </div>
  );
}
