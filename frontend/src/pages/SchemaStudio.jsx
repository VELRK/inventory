import { useEffect, useState } from 'react';
import { api } from '../api';
import { Field } from '../components/ui';

export default function SchemaStudio() {
  const [tables, setTables] = useState([]);
  const [table, setTable] = useState('projects');
  const [cols, setCols] = useState([]);
  const [sql, setSql] = useState('SELECT id, name, city FROM projects LIMIT 10');
  const [rows, setRows] = useState(null);
  const [form, setForm] = useState({ column: '', type: 'VARCHAR', length: '100', nullable: true, default: '' });
  const [err, setErr] = useState('');
  const [msg, setMsg] = useState('');
  const [logs, setLogs] = useState([]);

  const refresh = () => {
    api('/schema/tables').then((r) => setTables(r.data));
    api(`/schema/columns?table=${table}`).then((r) => setCols(r.data));
    api('/schema/logs').then((r) => setLogs(r.data));
  };
  useEffect(() => { refresh(); }, [table]);

  async function runQuery(e) {
    e?.preventDefault();
    setErr(''); setMsg('');
    try {
      const r = await api('/schema/query', { method: 'POST', body: { sql } });
      setRows(r.data.rows);
      setMsg(`${r.data.count} rows`);
    } catch (ex) { setErr(ex.message); setRows(null); }
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

  return (
    <div>
      <h1 className="page-title">Schema Studio</h1>
      <p className="muted">Add columns or run SELECT. DELETE, DROP and TRUNCATE are blocked from the frontend.</p>
      {err && <div className="alert alert-err">{err}</div>}
      {msg && <div className="alert alert-ok">{msg}</div>}
      <div className="grid grid-2" style={{ marginTop: 16 }}>
        <div className="card">
          <Field label="Table">
            <select className="select" value={table} onChange={(e) => setTable(e.target.value)}>
              {tables.map((t) => <option key={t.name} value={t.name}>{t.name}</option>)}
            </select>
          </Field>
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
          <h3>Safe query</h3>
          <textarea className="textarea" rows={6} value={sql} onChange={(e) => setSql(e.target.value)} />
          <div className="btn-row" style={{ margin: '10px 0' }}>
            <button className="btn btn-gold" onClick={runQuery}>Run</button>
            <button className="btn btn-outline" onClick={() => setSql('DROP TABLE users')}>Try DROP (blocked)</button>
          </div>
          {rows && (
            <div style={{ overflow: 'auto' }}>
              <table className="table">
                <thead><tr>{Object.keys(rows[0] || {}).map((k) => <th key={k}>{k}</th>)}</tr></thead>
                <tbody>{rows.map((r, i) => <tr key={i}>{Object.values(r).map((v, j) => <td key={j}>{String(v)}</td>)}</tr>)}</tbody>
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
