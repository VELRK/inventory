import { useEffect, useState } from 'react';
import { api, fmt, getUser } from '../api';
import { Badge, Field, Modal, Pager, RowActions, confirmDelete } from '../components/ui';

const blankBooking = {
  unit_id: '', company_id: '', customer_name: '', customer_phone: '', customer_email: '',
  amount: '', booking_date: new Date().toISOString().slice(0, 10), status: 'confirmed', payment_status: 'partial', notes: '',
};
const blankReg = {
  unit_id: '', company_id: '', customer_name: '', customer_phone: '', customer_email: '',
  amount: '', registration_date: new Date().toISOString().slice(0, 10), status: 'confirmed', payment_status: 'paid', notes: '',
};

export default function Reports() {
  const me = getUser();
  const admin = me?.role === 'promoter_admin';
  const canBook = admin || me?.role === 'marketing_team_admin';
  const [type, setType] = useState('bookings');
  const [filters, setFilters] = useState({ company_id: '', project_id: '', status: '', from: '', to: '' });
  const [opts, setOpts] = useState({ companies: [], projects: [] });
  const [units, setUnits] = useState([]);
  const [data, setData] = useState(null);
  const [limit, setLimit] = useState(10);
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState(blankBooking);
  const [err, setErr] = useState('');

  const qs = () => new URLSearchParams({ type, ...filters, page: data?.page || 1, limit }).toString();
  const load = (page = 1, lim = limit) => {
    const p = new URLSearchParams({ type, ...Object.fromEntries(Object.entries(filters).filter(([,v]) => v)), page, limit: lim });
    api(`/reports?${p}`).then((r) => setData(r.data)).catch((e) => setErr(e.message));
  };
  useEffect(() => {
    api('/reports/filters').then((r) => setOpts(r.data)).catch(() => {});
    api('/inventory?limit=100&status=available').then((r) => setUnits(r.data.items || [])).catch(() => {});
    api('/inventory?limit=100&status=on_hold').then((r) => {
      setUnits((prev) => {
        const map = {};
        [...prev, ...(r.data.items || [])].forEach((u) => { map[u.id] = u; });
        return Object.values(map);
      });
    }).catch(() => {});
  }, []);
  useEffect(() => {
    if (!admin && type === 'registrations') setType('bookings');
    else load(1);
  }, [type]);

  const endpoint = type === 'bookings' ? '/bookings' : '/registrations';
  const label = type === 'bookings' ? 'booking' : 'registration';

  function openAdd() {
    setEditing(null);
    setForm(type === 'bookings' ? { ...blankBooking } : { ...blankReg });
    setOpen(true);
  }
  function openEdit(row) {
    setEditing(row);
    if (type === 'bookings') {
      setForm({
        unit_id: row.unit_id, company_id: row.company_id || '', customer_name: row.customer_name || '',
        customer_phone: row.customer_phone || '', customer_email: row.customer_email || '',
        amount: row.amount, booking_date: row.booking_date || '', status: row.status,
        payment_status: row.payment_status, notes: row.notes || '',
      });
    } else {
      setForm({
        unit_id: row.unit_id, company_id: row.company_id || '', customer_name: row.customer_name || '',
        customer_phone: row.customer_phone || '', customer_email: row.customer_email || '',
        amount: row.amount, registration_date: row.registration_date || '', status: row.status,
        payment_status: row.payment_status, notes: row.notes || '',
      });
    }
    setOpen(true);
  }

  async function save(e) {
    e.preventDefault();
    try {
      if (editing) await api(`${endpoint}/${editing.id}`, { method: 'PUT', body: form });
      else await api(endpoint, { method: 'POST', body: form });
      setOpen(false);
      load(editing ? data.page : 1);
    } catch (ex) { setErr(ex.message); }
  }

  async function remove(row) {
    if (!confirmDelete(label)) return;
    try {
      await api(`${endpoint}/${row.id}`, { method: 'DELETE' });
      load(data.page);
    } catch (ex) { setErr(ex.message); }
  }

  const stats = data?.quick_stats || {};
  return (
    <div>
      <div className="toolbar">
        <h1 className="page-title" style={{ marginRight: 'auto' }}>Bookings</h1>
        {canBook && (type === 'bookings' || admin) && (
          <button className="btn btn-gold" onClick={openAdd}>+ Add {type === 'bookings' ? 'Booking' : 'Registration'}</button>
        )}
      </div>
      {err && <div className="alert alert-err">{err}</div>}
      <div className="card" style={{ margin: '16px 0' }}>
        <div className="grid grid-2">
          <label><span className="label">Marketing company</span>
            <select className="select" value={filters.company_id} onChange={(e) => setFilters({ ...filters, company_id: e.target.value })}>
              <option value="">All companies</option>
              {opts.companies.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
            </select>
          </label>
          <label><span className="label">Project</span>
            <select className="select" value={filters.project_id} onChange={(e) => setFilters({ ...filters, project_id: e.target.value })}>
              <option value="">All projects</option>
              {opts.projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
            </select>
          </label>
          <label><span className="label">From</span><input className="input" type="date" value={filters.from} onChange={(e) => setFilters({ ...filters, from: e.target.value })} /></label>
          <label><span className="label">To</span><input className="input" type="date" value={filters.to} onChange={(e) => setFilters({ ...filters, to: e.target.value })} /></label>
        </div>
        <div className="btn-row" style={{ marginTop: 14 }}>
          <button className="btn btn-outline" onClick={() => { setFilters({ company_id: '', project_id: '', status: '', from: '', to: '' }); }}>Reset</button>
          <button className="btn btn-gold" onClick={() => load(1)}>Apply Filters</button>
          <a className="btn btn-outline" href={`/plots/index.php/api/reports/export?${qs()}`} target="_blank" rel="noreferrer">Export CSV</a>
        </div>
      </div>
      <div className="grid grid-4">
        <div className="card stat"><div className="k" style={{ color: 'var(--info)' }}>Total Bookings</div><div className="v">{stats.total_bookings || 0}</div></div>
        <div className="card stat"><div className="k" style={{ color: 'var(--success)' }}>Total Registrations</div><div className="v">{stats.total_registrations || 0}</div></div>
        <div className="card stat"><div className="k" style={{ color: 'var(--teal)' }}>Total Value</div><div className="v" style={{ fontSize: 20 }}>{stats.total_value_formatted}</div></div>
        <div className="card stat"><div className="k" style={{ color: 'var(--warning)' }}>Customers</div><div className="v">{stats.total_customers || 0}</div></div>
      </div>
      <div className="tabs" style={{ marginTop: 18 }}>
        <button className={`tab ${type==='bookings'?'active':''}`} onClick={() => setType('bookings')}>Bookings ({stats.total_bookings || 0})</button>
        {admin && <button className={`tab ${type==='registrations'?'active':''}`} onClick={() => setType('registrations')}>Registrations ({stats.total_registrations || 0})</button>}
      </div>
      {(data?.items || []).map((row) => (
        <div key={row.id} className="list-card">
          <div className="avatar">{row.initials}</div>
          <div>
            <strong>{row.customer_name}</strong> · Unit {row.unit_no}
            <div><span className="badge b-available">{row.company_name}</span></div>
            <div className="muted">{row.project_name}, {row.project_city}</div>
          </div>
          <div style={{ textAlign: 'right' }}>
            <div className="price" style={{ fontSize: 16 }}>{fmt(row.amount)}</div>
            <div className="muted">{row.booking_date || row.registration_date}</div>
            <Badge status={row.status} />
            {((type === 'bookings' && canBook) || (type === 'registrations' && admin)) && (
              <RowActions onEdit={() => openEdit(row)} onDelete={() => remove(row)} />
            )}
          </div>
        </div>
      ))}
      {data && <Pager {...data} onPage={load} onLimit={(n) => { setLimit(n); load(1, n); }} />}

      {open && (
        <Modal title={`${editing ? 'Edit' : 'Add'} ${label}`} onClose={() => setOpen(false)}>
          <form onSubmit={save} className="grid">
            {!editing && (
              <Field label="Unit">
                <select className="select" value={form.unit_id} onChange={(e) => {
                  const u = units.find((x) => String(x.id) === e.target.value);
                  setForm({ ...form, unit_id: e.target.value, amount: u ? u.price : form.amount });
                }}>
                  <option value="">Select unit</option>
                  {units.map((u) => <option key={u.id} value={u.id}>{u.unit_no} · {u.project_name} · {u.status}</option>)}
                </select>
              </Field>
            )}
            <Field label="Customer name"><input className="input" value={form.customer_name} onChange={(e) => setForm({ ...form, customer_name: e.target.value })} /></Field>
            <div className="grid grid-2">
              <Field label="Phone"><input className="input" value={form.customer_phone} onChange={(e) => setForm({ ...form, customer_phone: e.target.value })} /></Field>
              <Field label="Email"><input className="input" value={form.customer_email} onChange={(e) => setForm({ ...form, customer_email: e.target.value })} /></Field>
            </div>
            <div className="grid grid-2">
              <Field label="Amount"><input className="input" type="number" value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} /></Field>
              <Field label="Company">
                <select className="select" value={form.company_id} onChange={(e) => setForm({ ...form, company_id: e.target.value })}>
                  <option value="">None</option>
                  {opts.companies.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
              </Field>
            </div>
            <div className="grid grid-2">
              {type === 'bookings' ? (
                <Field label="Booking date"><input className="input" type="date" value={form.booking_date} onChange={(e) => setForm({ ...form, booking_date: e.target.value })} /></Field>
              ) : (
                <Field label="Registration date"><input className="input" type="date" value={form.registration_date} onChange={(e) => setForm({ ...form, registration_date: e.target.value })} /></Field>
              )}
              <Field label="Status">
                <select className="select" value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                  <option value="pending">Pending</option>
                  <option value="confirmed">Confirmed</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </Field>
            </div>
            <Field label="Payment">
              <select className="select" value={form.payment_status} onChange={(e) => setForm({ ...form, payment_status: e.target.value })}>
                <option value="unpaid">Unpaid</option>
                <option value="partial">Partial</option>
                <option value="paid">Paid</option>
              </select>
            </Field>
            <Field label="Notes"><textarea className="textarea" rows={2} value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} /></Field>
            <button className="btn btn-gold">{editing ? `Update ${label}` : `Save ${label}`}</button>
          </form>
        </Modal>
      )}
    </div>
  );
}
