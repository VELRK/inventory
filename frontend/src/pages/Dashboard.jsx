import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { PieChart, Pie, Cell, Tooltip, ResponsiveContainer, BarChart, Bar, XAxis, YAxis, CartesianGrid, Legend, LineChart, Line } from 'recharts';
import { api, fmt } from '../api';

const COLORS = ['#28a745', '#e8a317', '#1f6f6d', '#5b4b8a', '#8a94a6'];

export default function Dashboard() {
  const [dash, setDash] = useState(null);
  const [charts, setCharts] = useState(null);
  const [err, setErr] = useState('');

  useEffect(() => {
    Promise.all([api('/dashboard'), api('/dashboard/charts')])
      .then(([d, c]) => { setDash(d.data); setCharts(c.data); })
      .catch((e) => setErr(e.message));
  }, []);

  if (err) return <div className="alert alert-err">{err}</div>;
  if (!dash) return <p className="muted">Loading dashboard…</p>;
  const inv = dash.inventory || {};

  return (
    <div>
      <h1 className="page-title">{dash.greeting}</h1>
      <p className="muted">Live inventory across assigned projects.</p>
      <div className="grid grid-2" style={{ marginTop: 18 }}>
        <div className="card hero-stat stat">
          <div className="k">Total Projects</div>
          <div className="v">{dash.total_projects}</div>
        </div>
        <div className="grid grid-2">
          <div className="card stat"><div className="k" style={{ color: 'var(--success)' }}>Available</div><div className="v">{inv.available}</div></div>
          <div className="card stat"><div className="k" style={{ color: 'var(--warning)' }}>Blocked</div><div className="v">{inv.blocked}</div></div>
          <div className="card stat"><div className="k" style={{ color: 'var(--teal)' }}>Booked</div><div className="v">{inv.booked}</div></div>
          <div className="card stat"><div className="k" style={{ color: 'var(--registered)' }}>Registered</div><div className="v">{inv.registered}</div></div>
        </div>
      </div>

      <div className="grid grid-2" style={{ marginTop: 18 }}>
        <div className="card">
          <h3>Inventory mix</h3>
          <div style={{ height: 260 }}>
            <ResponsiveContainer>
              <PieChart>
                <Pie data={charts?.status_pie || []} dataKey="value" nameKey="name" innerRadius={50} outerRadius={90}>
                  {(charts?.status_pie || []).map((_, i) => <Cell key={i} fill={COLORS[i % COLORS.length]} />)}
                </Pie>
                <Tooltip />
                <Legend />
              </PieChart>
            </ResponsiveContainer>
          </div>
        </div>
        <div className="card">
          <h3>Project breakdown</h3>
          <div style={{ height: 260 }}>
            <ResponsiveContainer>
              <BarChart data={charts?.project_breakdown || []}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="name" />
                <YAxis />
                <Tooltip />
                <Legend />
                <Bar dataKey="available" fill="#28a745" />
                <Bar dataKey="blocked" fill="#e8a317" />
                <Bar dataKey="booked" fill="#1f6f6d" />
                <Bar dataKey="registered" fill="#5b4b8a" />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>
      </div>

      <div className="card" style={{ marginTop: 18 }}>
        <h3>Monthly booking value</h3>
        <div style={{ height: 240 }}>
          <ResponsiveContainer>
            <LineChart data={charts?.monthly || []}>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis dataKey="ym" />
              <YAxis />
              <Tooltip formatter={(v) => fmt(v)} />
              <Line type="monotone" dataKey="value" stroke="#c5a059" strokeWidth={3} />
            </LineChart>
          </ResponsiveContainer>
        </div>
      </div>

      <h3 style={{ marginTop: 28 }}>Recent projects</h3>
      {(dash.recent_projects || []).map((p) => (
        <Link key={p.id} to={`/inventory?project_id=${p.id}`} className="list-card">
          <div className="thumb" />
          <div>
            <strong>{p.name}</strong>
            <div className="muted">{p.location}, {p.city}</div>
          </div>
          <span className="badge b-available">{p.counts?.available || 0} Available</span>
        </Link>
      ))}
    </div>
  );
}
