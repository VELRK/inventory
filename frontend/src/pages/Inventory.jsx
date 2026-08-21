import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { api, fmt, getUser } from '../api';
import { Badge, Field, Modal, Pager, RowActions, confirmDelete } from '../components/ui';

const statuses = ['', 'available', 'on_hold', 'booked', 'registered'];
const blankUnit = { project_id: '', unit_no: '', block_phase: '', plot_type: 'Residential Plot', area_sqft: 1200, facing: 'East', road_width_ft: 30, dimensions: '30x40', price: 3600000, status: 'available', remarks: '' };
const blankBook = {
  customer_name: '', customer_phone: '', customer_email: '', company_id: '',
  amount: '', booking_date: new Date().toISOString().slice(0, 10), status: 'confirmed', payment_status: 'partial', notes: '',
};

export default function Inventory() {
  const me = getUser();
  const admin = me?.role === 'promoter_admin';
  const canBook = me?.role === 'promoter_admin' || me?.role === 'marketing_team_admin';
  const [params, setParams] = useSearchParams();
  const projectId = params.get('project_id') || '';
  const [projects, setProjects] = useState([]);
  const [companies, setCompanies] = useState([]);
  const [data, setData] = useState({ items: [], stats: {}, total: 0, page: 1, pages: 1, limit: 10 });
  const [limit, setLimit] = useState(10);
  const [status, setStatus] = useState('');
  const [q, setQ] = useState('');
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [detail, setDetail] = useState(null);
  const [reqOpen, setReqOpen] = useState(false);
  const [bookOpen, setBookOpen] = useState(false);
  const [bulk, setBulk] = useState([]);
  const [form, setForm] = useState({ ...blankUnit, project_id: projectId });
  const [req, setReq] = useState({ customer_name: '', customer_phone: '', customer_email: '', expected_booking_date: '', remarks: '' });
  const [book, setBook] = useState({ ...blankBook });
  const [err, setErr] = useState('');
  const [msg, setMsg] = useState('');

  function load(page = 1, lim = limit) {
    const qs = new URLSearchParams({ page, limit: lim, q, status, project_id: projectId });
    api(`/inventory?${qs}`).then((r) => setData(r.data)).catch((e) => setErr(e.message));
  }
  useEffect(() => {
    api('/projects?limit=100').then((r) => setProjects(r.data.items || []));
    if (admin) api('/companies?limit=100').then((r) => setCompanies(r.data.items || [])).catch(() => {});
    load(1);
  }, [projectId, status]);

  function openAdd() {
    setEditing(null);
    setForm({ ...blankUnit, project_id: projectId });
    setOpen(true);
  }
  function openEdit(u) {
    setEditing(u);
    setForm({
      project_id: u.project_id,
      unit_no: u.unit_no || '',
      block_phase: u.block_phase || '',
      plot_type: u.plot_type || 'Residential Plot',
      area_sqft: u.area_sqft,
      facing: u.facing || '',
      road_width_ft: u.road_width_ft || '',
      dimensions: u.dimensions || '',
      price: u.price,
      status: u.status,
      remarks: u.remarks || '',
    });
    setOpen(true);
  }

  async function saveUnit(e) {
    e.preventDefault();
    try {
      if (editing) await api(`/inventory/${editing.id}`, { method: 'PUT', body: form });
      else await api('/inventory', { method: 'POST', body: form });
      setOpen(false);
      load(editing ? data.page : 1);
    } catch (ex) { setErr(ex.message); }
  }
  async function remove(u) {
    if (!confirmDelete('unit')) return;
    try {
      await api(`/inventory/${u.id}`, { method: 'DELETE' });
      load(data.page);
    } catch (ex) { setErr(ex.message); }
  }
  async function submitRequest(e) {
    e.preventDefault();
    try {
      await api('/requests', { method: 'POST', body: { ...req, unit_id: detail.id } });
      setReqOpen(false); setMsg('Hold request submitted.'); load();
    } catch (ex) { setErr(ex.message); }
  }
  async function submitBook(e) {
    e.preventDefault();
    try {
      await api('/bookings', {
        method: 'POST',
        body: {
          ...book,
          unit_id: detail.id,
          amount: book.amount || detail.price,
          company_id: book.company_id || me?.company_id || '',
        },
      });
      setBookOpen(false);
      setDetail(null);
      setMsg('Booking created.');
      load();
    } catch (ex) { setErr(ex.message); }
  }
  async function bulkUpdate() {
    try {
      await api('/inventory/bulk', { method: 'POST', body: { ids: bulk, action: 'change_status', status: 'on_hold', remarks: 'Bulk hold' } });
      setBulk([]); load();
    } catch (ex) { setErr(ex.message); }
  }

  const stats = data.stats || {};
  const bookable = detail && (detail.status === 'available' || detail.status === 'on_hold');

  return (
    <div>
      <div className="toolbar">
        <h1 className="page-title" style={{ marginRight: 'auto' }}>Inventory</h1>
        <select className="select" value={projectId} onChange={(e) => setParams(e.target.value ? { project_id: e.target.value } : {})}>
          <option value="">All projects</option>
          {projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
        </select>
        <input className="input search" placeholder="Unit no" value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && load(1)} />
        {admin && <button className="btn btn-gold" onClick={openAdd}>+ Add Unit</button>}
        {admin && bulk.length > 0 && <button className="btn btn-outline" onClick={bulkUpdate}>Hold {bulk.length} units</button>}
      </div>
      {err && <div className="alert alert-err">{err}</div>}
      {msg && <div className="alert alert-ok">{msg}</div>}
      <div className="grid grid-5" style={{ marginBottom: 16 }}>
        {['total', 'available', 'on_hold', 'booked', 'registered'].map((k) => (
          <div key={k} className="card stat"><div className="k">{k.replace('_', ' ')}</div><div className="v">{stats[k] || 0}</div></div>
        ))}
      </div>
      <div className="chips">
        {statuses.map((s) => (
          <button key={s || 'all'} className={`chip ${status === s ? 'btn-teal' : ''}`} style={status === s ? { background: 'var(--teal)', color: '#fff' } : {}} onClick={() => setStatus(s)}>
            {s ? s.replace('_', ' ') : 'All'}
          </button>
        ))}
      </div>
      {data.items.map((u) => (
        <div key={u.id} className="list-card">
          {admin && <input type="checkbox" checked={bulk.includes(u.id)} onChange={(e) => setBulk(e.target.checked ? [...bulk, u.id] : bulk.filter((i) => i !== u.id))} />}
          <div>
            <strong>{u.unit_no}</strong> · {u.project_name}
            <div className="unit-meta">{u.area_sqft} sq.ft · {u.facing} · {u.road_width_ft} ft road · {u.plot_type}</div>
            <div className="price">{fmt(u.price)}</div>
          </div>
          <div style={{ textAlign: 'right' }}>
            <Badge status={u.status} />
            <RowActions
              onEdit={admin ? () => openEdit(u) : undefined}
              onDelete={admin ? () => remove(u) : undefined}
            >
              <button className="btn btn-ghost btn-sm" onClick={() => setDetail(u)}>View</button>
            </RowActions>
          </div>
        </div>
      ))}
      <Pager {...data} onPage={load} onLimit={(n) => { setLimit(n); load(1, n); }} />

      {open && (
        <Modal title={editing ? 'Edit unit' : 'Add unit'} onClose={() => setOpen(false)}>
          <form onSubmit={saveUnit} className="grid">
            <Field label="Project">
              <select className="select" value={form.project_id} onChange={(e) => setForm({ ...form, project_id: e.target.value })}>
                <option value="">Select</option>
                {projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
              </select>
            </Field>
            <div className="grid grid-2">
              <Field label="Unit no"><input className="input" value={form.unit_no} onChange={(e) => setForm({ ...form, unit_no: e.target.value })} /></Field>
              <Field label="Block / Phase"><input className="input" value={form.block_phase} onChange={(e) => setForm({ ...form, block_phase: e.target.value })} /></Field>
            </div>
            <div className="grid grid-2">
              <Field label="Plot type"><input className="input" value={form.plot_type} onChange={(e) => setForm({ ...form, plot_type: e.target.value })} /></Field>
              <Field label="Dimensions"><input className="input" value={form.dimensions} onChange={(e) => setForm({ ...form, dimensions: e.target.value })} /></Field>
            </div>
            <div className="grid grid-2">
              <Field label="Area sq.ft"><input className="input" type="number" value={form.area_sqft} onChange={(e) => setForm({ ...form, area_sqft: e.target.value })} /></Field>
              <Field label="Price"><input className="input" type="number" value={form.price} onChange={(e) => setForm({ ...form, price: e.target.value })} /></Field>
            </div>
            <div className="grid grid-2">
              <Field label="Facing"><input className="input" value={form.facing} onChange={(e) => setForm({ ...form, facing: e.target.value })} /></Field>
              <Field label="Road width"><input className="input" value={form.road_width_ft} onChange={(e) => setForm({ ...form, road_width_ft: e.target.value })} /></Field>
            </div>
            {editing && (
              <Field label="Status">
                <select className="select" value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                  {statuses.filter(Boolean).map((s) => <option key={s} value={s}>{s.replace('_', ' ')}</option>)}
                </select>
              </Field>
            )}
            <Field label="Remarks"><textarea className="textarea" rows={2} value={form.remarks} onChange={(e) => setForm({ ...form, remarks: e.target.value })} /></Field>
            <button className="btn btn-gold">{editing ? 'Update Unit' : 'Save Unit'}</button>
          </form>
        </Modal>
      )}

      {detail && (
        <Modal title={`${detail.unit_no} · ${detail.project_name}`} onClose={() => setDetail(null)}>
          <Badge status={detail.status} />
          <p className="price">{fmt(detail.price)}</p>
          <p className="muted">{detail.area_sqft} sq.ft · {detail.facing} · {detail.dimensions} · {detail.approval_details}</p>
          <p>{detail.remarks}</p>
          <div className="btn-row" style={{ marginTop: 12 }}>
            {admin && (
              <>
                <button className="btn btn-outline" onClick={() => { setDetail(null); openEdit(detail); }}>Edit</button>
                <button className="btn btn-danger" onClick={() => { setDetail(null); remove(detail); }}>Delete</button>
              </>
            )}
            {canBook && bookable && (
              <button className="btn btn-gold" onClick={() => {
                setBook({ ...blankBook, amount: detail.price, company_id: me?.company_id || '' });
                setBookOpen(true);
              }}>Book unit</button>
            )}
            {!admin && detail.status === 'available' && (
              <button className="btn btn-outline" onClick={() => setReqOpen(true)}>Request hold</button>
            )}
          </div>
        </Modal>
      )}
      {reqOpen && detail && (
        <Modal title={`Request hold · ${detail.unit_no}`} onClose={() => setReqOpen(false)}>
          <form onSubmit={submitRequest} className="grid">
            <Field label="Customer name"><input className="input" value={req.customer_name} onChange={(e) => setReq({ ...req, customer_name: e.target.value })} /></Field>
            <Field label="Phone"><input className="input" value={req.customer_phone} onChange={(e) => setReq({ ...req, customer_phone: e.target.value })} /></Field>
            <Field label="Email"><input className="input" value={req.customer_email} onChange={(e) => setReq({ ...req, customer_email: e.target.value })} /></Field>
            <Field label="Expected booking date"><input className="input" type="date" value={req.expected_booking_date} onChange={(e) => setReq({ ...req, expected_booking_date: e.target.value })} /></Field>
            <Field label="Remarks"><textarea className="textarea" rows={3} value={req.remarks} onChange={(e) => setReq({ ...req, remarks: e.target.value })} /></Field>
            <button className="btn btn-gold">Submit Request</button>
          </form>
        </Modal>
      )}
      {bookOpen && detail && (
        <Modal title={`Book ${detail.unit_no}`} onClose={() => setBookOpen(false)}>
          <form onSubmit={submitBook} className="grid">
            <Field label="Customer name"><input className="input" required value={book.customer_name} onChange={(e) => setBook({ ...book, customer_name: e.target.value })} /></Field>
            <div className="grid grid-2">
              <Field label="Phone"><input className="input" value={book.customer_phone} onChange={(e) => setBook({ ...book, customer_phone: e.target.value })} /></Field>
              <Field label="Email"><input className="input" value={book.customer_email} onChange={(e) => setBook({ ...book, customer_email: e.target.value })} /></Field>
            </div>
            <div className="grid grid-2">
              <Field label="Amount"><input className="input" type="number" value={book.amount} onChange={(e) => setBook({ ...book, amount: e.target.value })} /></Field>
              <Field label="Booking date"><input className="input" type="date" value={book.booking_date} onChange={(e) => setBook({ ...book, booking_date: e.target.value })} /></Field>
            </div>
            {admin && (
              <Field label="Company">
                <select className="select" value={book.company_id} onChange={(e) => setBook({ ...book, company_id: e.target.value })}>
                  <option value="">None</option>
                  {companies.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
              </Field>
            )}
            <Field label="Notes"><textarea className="textarea" rows={2} value={book.notes} onChange={(e) => setBook({ ...book, notes: e.target.value })} /></Field>
            <button className="btn btn-gold">Confirm booking</button>
          </form>
        </Modal>
      )}
    </div>
  );
}
