import { useEffect, useState } from 'react';
import { api, getUser } from '../api';
import { Badge, Field, Modal, Pager, RowActions, confirmDelete } from '../components/ui';

export default function Requests() {
  const me = getUser();
  const admin = me?.role === 'promoter_admin';
  const teamAdmin = me?.role === 'marketing_team_admin';
  const [tab, setTab] = useState('');
  const [data, setData] = useState({ items: [], total: 0, page: 1, pages: 1, limit: 10 });
  const [limit, setLimit] = useState(10);
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState({ customer_name: '', customer_phone: '', customer_email: '', expected_booking_date: '', remarks: '' });
  const [err, setErr] = useState('');

  const load = (page = 1, lim = limit) => api(`/requests?status=${tab}&page=${page}&limit=${lim}`).then((r) => setData(r.data)).catch((e) => setErr(e.message));
  useEffect(() => { load(1); }, [tab]);

  function canManage(r) {
    if (r.status !== 'pending') return false;
    return admin || teamAdmin || r.requested_by === me.id;
  }

  function openEdit(r) {
    setEditing(r);
    setForm({
      customer_name: r.customer_name || '',
      customer_phone: r.customer_phone || '',
      customer_email: r.customer_email || '',
      expected_booking_date: r.expected_booking_date || '',
      remarks: r.remarks || '',
    });
    setOpen(true);
  }

  async function save(e) {
    e.preventDefault();
    try {
      await api(`/requests/${editing.id}`, { method: 'PUT', body: form });
      setOpen(false);
      load(data.page);
    } catch (ex) { setErr(ex.message); }
  }

  async function remove(r) {
    if (!confirmDelete('block request')) return;
    try {
      await api(`/requests/${r.id}`, { method: 'DELETE' });
      load(data.page);
    } catch (e) { setErr(e.message); }
  }

  async function review(id, decision) {
    const notes = decision === 'rejected' ? prompt('Rejection notes') : prompt('Approval notes', 'Hold 7 days');
    try {
      await api(`/requests/${id}/review`, { method: 'POST', body: { decision, review_notes: notes } });
      load(data.page);
    } catch (e) { setErr(e.message); }
  }

  return (
    <div>
      <h1 className="page-title">{admin ? 'Block requests' : 'My requests'}</h1>
      {err && <div className="alert alert-err">{err}</div>}
      <div className="tabs">
        {[['','All'],['pending','Pending'],['approved','Approved'],['rejected','Rejected']].map(([v,l]) => (
          <button key={v} className={`tab ${tab===v?'active':''}`} onClick={() => setTab(v)}>{l}</button>
        ))}
      </div>
      {data.items.map((r) => (
        <div key={r.id} className="list-card">
          <div className="avatar">{r.unit_no?.slice(0,2)}</div>
          <div>
            <strong>{r.unit_no}</strong> · {r.project_name}
            <div className="muted">{r.customer_name} · {r.customer_phone} · {r.company_name}</div>
            <div className="muted">{r.created_at}</div>
          </div>
          <div style={{ textAlign: 'right' }}>
            <Badge status={r.status} />
            {admin && r.status === 'pending' && (
              <div className="btn-row" style={{ marginTop: 8, justifyContent: 'flex-end' }}>
                <button className="btn btn-gold btn-sm" onClick={() => review(r.id, 'approved')}>Approve</button>
                <button className="btn btn-outline btn-sm" onClick={() => review(r.id, 'rejected')}>Reject</button>
              </div>
            )}
            {canManage(r) && <RowActions onEdit={() => openEdit(r)} onDelete={() => remove(r)} />}
          </div>
        </div>
      ))}
      <Pager {...data} onPage={load} onLimit={(n) => { setLimit(n); load(1, n); }} />
      {open && editing && (
        <Modal title={`Edit request · ${editing.unit_no}`} onClose={() => setOpen(false)}>
          <form onSubmit={save} className="grid">
            <Field label="Customer name"><input className="input" value={form.customer_name} onChange={(e) => setForm({ ...form, customer_name: e.target.value })} /></Field>
            <Field label="Phone"><input className="input" value={form.customer_phone} onChange={(e) => setForm({ ...form, customer_phone: e.target.value })} /></Field>
            <Field label="Email"><input className="input" value={form.customer_email} onChange={(e) => setForm({ ...form, customer_email: e.target.value })} /></Field>
            <Field label="Expected booking date"><input className="input" type="date" value={form.expected_booking_date} onChange={(e) => setForm({ ...form, expected_booking_date: e.target.value })} /></Field>
            <Field label="Remarks"><textarea className="textarea" rows={3} value={form.remarks} onChange={(e) => setForm({ ...form, remarks: e.target.value })} /></Field>
            <button className="btn btn-gold">Update Request</button>
          </form>
        </Modal>
      )}
    </div>
  );
}
