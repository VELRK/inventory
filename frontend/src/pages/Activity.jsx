import { useEffect, useState } from 'react';
import { api } from '../api';
import { Pager } from '../components/ui';

export default function ActivityPage() {
  const [data, setData] = useState({ items: [], total: 0, page: 1, pages: 1, limit: 10 });
  const [limit, setLimit] = useState(10);
  const [q, setQ] = useState('');
  const load = (page = 1, lim = limit) => api(`/activity?q=${encodeURIComponent(q)}&page=${page}&limit=${lim}`).then((r) => setData(r.data));
  useEffect(() => { load(); }, []);
  return (
    <div>
      <div className="toolbar">
        <h1 className="page-title" style={{ marginRight: 'auto' }}>Activity log</h1>
        <input className="input search" value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && load(1)} placeholder="Search" />
      </div>
      {data.items.map((a) => (
        <div key={a.id} className="list-card">
          <div className="avatar">•</div>
          <div>
            <strong>{a.description}</strong>
            <div className="muted">by {a.user_name} · {a.created_at}</div>
          </div>
          <span className="chip">{a.action}</span>
        </div>
      ))}
      <Pager {...data} onPage={load} onLimit={(n) => { setLimit(n); load(1, n); }} />
    </div>
  );
}
