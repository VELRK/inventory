import { useEffect, useMemo, useState } from 'react';
import { api, getToken } from '../api';

export default function ApiTester() {
  const [tab, setTab] = useState('schema');
  const [catalog, setCatalog] = useState(null);
  const [ep, setEp] = useState(null);
  const [body, setBody] = useState('{}');
  const [out, setOut] = useState('');
  const [status, setStatus] = useState('');
  const [schema, setSchema] = useState(null);
  const [schemaErr, setSchemaErr] = useState('');
  const [q, setQ] = useState('');
  const [openTables, setOpenTables] = useState({});
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    api('/docs').then((r) => {
      setCatalog(r.data);
      const first = r.data.endpoints[0];
      setEp(first);
      setBody(JSON.stringify(first.input || {}, null, 2));
    }).catch((e) => setOut(e.message));
    api('/schema').then((r) => {
      setSchema(r.data);
      const open = {};
      (r.data.tables || []).forEach((t) => { open[t.name] = true; });
      setOpenTables(open);
    }).catch((e) => setSchemaErr(e.message));
  }, []);

  async function run() {
    if (!ep) return;
    const path = ep.path.replace('{id}', '1').split('?')[0];
    const extra = ep.path.includes('?') ? '?' + ep.path.split('?')[1] : '';
    let parsed = {};
    try { parsed = body ? JSON.parse(body) : {}; } catch { setOut('Invalid JSON body'); return; }
    const qs = ep.method === 'GET' && parsed && Object.keys(parsed).length
      ? '?' + new URLSearchParams(Object.fromEntries(Object.entries(parsed).filter(([,v]) => v !== '' && v != null))).toString()
      : extra;
    try {
      const json = await api(path + qs, {
        method: ep.method,
        body: ep.method === 'GET' ? undefined : parsed,
      });
      setStatus('200');
      setOut(JSON.stringify(json, null, 2));
    } catch (e) {
      setStatus(String(e.status || 'ERR'));
      setOut(JSON.stringify(e.payload || { message: e.message }, null, 2));
    }
  }

  const filtered = useMemo(() => {
    const tables = schema?.tables || [];
    const term = q.trim().toLowerCase();
    if (!term) return tables;
    return tables.map((t) => {
      const cols = (t.columns || []).filter((c) =>
        c.name.toLowerCase().includes(term) || String(c.type || '').toLowerCase().includes(term)
      );
      const tableHit = t.name.toLowerCase().includes(term);
      if (!tableHit && cols.length === 0) return null;
      return { ...t, columns: tableHit ? t.columns : cols };
    }).filter(Boolean);
  }, [schema, q]);

  async function copyJson() {
    try {
      await navigator.clipboard.writeText(JSON.stringify(schema, null, 2));
      setCopied(true);
      setTimeout(() => setCopied(false), 1600);
    } catch {
      setCopied(false);
    }
  }

  function downloadJson() {
    const blob = new Blob([JSON.stringify(schema, null, 2)], { type: 'application/json' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `${schema?.database || 'schema'}-columns.json`;
    a.click();
    URL.revokeObjectURL(a.href);
  }

  const groups = [...new Set((catalog?.endpoints || []).map((e) => e.group))];
  return (
    <div>
      <h1 className="page-title">API Tester</h1>
      <p className="muted">Postman-style runner plus full database column list for mobile app developers. Base {catalog?.base_url}.</p>
      <div className="tabs" style={{ marginTop: 16 }}>
        <button className={`tab ${tab === 'schema' ? 'active' : ''}`} onClick={() => setTab('schema')}>
          Database columns {schema ? `(${schema.table_count} tables · ${schema.column_count} columns)` : ''}
        </button>
        <button className={`tab ${tab === 'endpoints' ? 'active' : ''}`} onClick={() => setTab('endpoints')}>
          Endpoints
        </button>
      </div>

      {tab === 'schema' && (
        <div>
          {schemaErr && <div className="alert alert-err">{schemaErr}</div>}
          <div className="toolbar">
            <input className="input search" placeholder="Search table or column" value={q} onChange={(e) => setQ(e.target.value)} />
            <button type="button" className="btn btn-outline btn-sm" onClick={() => setOpenTables(Object.fromEntries((schema?.tables || []).map((t) => [t.name, true])))}>Expand all</button>
            <button type="button" className="btn btn-outline btn-sm" onClick={() => setOpenTables({})}>Collapse all</button>
            <button type="button" className="btn btn-gold btn-sm" onClick={copyJson}>{copied ? 'Copied' : 'Copy JSON'}</button>
            <button type="button" className="btn btn-outline btn-sm" onClick={downloadJson}>Download JSON</button>
          </div>
          <p className="muted" style={{ marginTop: 0 }}>
            GET <code>/api/schema</code> · database <strong>{schema?.database || '…'}</strong>
          </p>
          {(filtered).map((t) => (
            <div key={t.name} className="card" style={{ marginBottom: 12, padding: 0, overflow: 'hidden' }}>
              <button
                type="button"
                className="schema-table-head"
                onClick={() => setOpenTables((prev) => ({ ...prev, [t.name]: !prev[t.name] }))}
              >
                <strong>{t.name}</strong>
                <span className="muted">{t.column_count} columns{t.engine ? ` · ${t.engine}` : ''}{t.row_estimate ? ` · ~${t.row_estimate} rows` : ''}</span>
                <span className="muted">{openTables[t.name] ? '▾' : '▸'}</span>
              </button>
              {openTables[t.name] && (
                <div style={{ overflow: 'auto' }}>
                  <table className="table">
                    <thead>
                      <tr>
                        <th>#</th>
                        <th>Column</th>
                        <th>Type</th>
                        <th>Null</th>
                        <th>Key</th>
                        <th>Default</th>
                        <th>Extra</th>
                      </tr>
                    </thead>
                    <tbody>
                      {(t.columns || []).map((c) => (
                        <tr key={c.name}>
                          <td className="muted">{c.position}</td>
                          <td><strong>{c.name}</strong>{c.primary ? <span className="badge b-booked" style={{ marginLeft: 8 }}>PK</span> : null}</td>
                          <td><code>{c.type}</code></td>
                          <td>{c.nullable ? 'YES' : 'NO'}</td>
                          <td>{c.col_key || '—'}</td>
                          <td className="muted">{c.col_default === null || c.col_default === undefined ? 'NULL' : String(c.col_default)}</td>
                          <td className="muted">{c.extra || '—'}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          ))}
          {!schema && !schemaErr && <p className="muted">Loading schema…</p>}
        </div>
      )}

      {tab === 'endpoints' && (
        <div className="grid grid-2" style={{ marginTop: 16 }}>
          <div className="card" style={{ maxHeight: '70vh', overflow: 'auto' }}>
            {(groups).map((g) => (
              <div key={g}>
                <h4 style={{ color: 'var(--teal)' }}>{g}</h4>
                {(catalog?.endpoints || []).filter((e) => e.group === g).map((e) => (
                  <button key={e.method + e.path} className="nav-item" style={{ color: 'var(--navy)', width: '100%', textAlign: 'left' }} onClick={() => { setEp(e); setBody(JSON.stringify(e.input || {}, null, 2)); }}>
                    <strong style={{ color: e.method === 'GET' ? 'var(--success)' : 'var(--gold)' }}>{e.method}</strong> {e.path}
                  </button>
                ))}
              </div>
            ))}
          </div>
          <div className="card">
            {ep && (
              <>
                <h3>{ep.method} {ep.path}</h3>
                <p>{ep.summary}</p>
                {ep.roles && <p className="muted">Roles: {ep.roles.join(', ')}</p>}
                <p className="muted">Token: {getToken() ? 'present' : 'none — login first'}</p>
                <span className="label">Input JSON</span>
                <textarea className="textarea" rows={10} value={body} onChange={(e) => setBody(e.target.value)} />
                <div className="btn-row" style={{ margin: '12px 0' }}>
                  <button className="btn btn-gold" onClick={run}>Send</button>
                  <span className="chip">HTTP {status || '—'}</span>
                </div>
                <span className="label">Sample output</span>
                <pre className="json-box">{JSON.stringify(ep.output, null, 2)}</pre>
                <span className="label">Live response</span>
                <pre className="json-box">{out}</pre>
              </>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
