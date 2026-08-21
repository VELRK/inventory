import { ChevronLeft, ChevronRight, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { IMAGE_HINT, fileUrl, uploadImage } from '../api';

export function Badge({ status }) {
  const key = String(status || '').toLowerCase();
  return <span className={`badge b-${key}`}>{status?.replaceAll('_', ' ')}</span>;
}

export function Modal({ title, children, onClose }) {
  return (
    <div className="modal-back" onClick={onClose}>
      <div className="modal" onClick={(e) => e.stopPropagation()}>
        <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 12 }}>
          <h3 style={{ margin: 0 }}>{title}</h3>
          <button className="btn btn-ghost" onClick={onClose}>Close</button>
        </div>
        {children}
      </div>
    </div>
  );
}

function pageWindow(current, totalPages) {
  if (totalPages <= 7) {
    return Array.from({ length: totalPages }, (_, i) => i + 1);
  }
  const set = new Set([1, totalPages, current, current - 1, current + 1, current - 2, current + 2]);
  const nums = [...set].filter((n) => n >= 1 && n <= totalPages).sort((a, b) => a - b);
  const out = [];
  nums.forEach((n, i) => {
    if (i > 0 && n - nums[i - 1] > 1) out.push('…');
    out.push(n);
  });
  return out;
}

export function Pager({ page, pages, total, limit, onPage, onLimit }) {
  const totalPages = Math.max(1, Number(pages) || 1);
  const current = Math.max(1, Number(page) || 1);
  const lim = Number(limit) || 10;
  const count = Number(total) || 0;
  const from = count === 0 ? 0 : (current - 1) * lim + 1;
  const to = Math.min(current * lim, count);
  const items = pageWindow(current, totalPages);

  return (
    <div className="pager">
      <div className="pager-left">
        <label className="pager-limit">
          <select
            className="select pager-select"
            value={lim}
            onChange={(e) => onLimit?.(Number(e.target.value))}
            disabled={!onLimit}
          >
            {[10, 25, 50].map((n) => <option key={n} value={n}>{n} per page</option>)}
          </select>
        </label>
        <span className="pager-range">Showing {from} to {to} of {count}</span>
      </div>
      <div className="page-btns">
        <button type="button" className="page-nav" disabled={current <= 1} onClick={() => onPage(current - 1)}>
          <ChevronLeft size={16} /> Previous
        </button>
        {items.map((item, i) => (
          item === '…' ? (
            <span key={`e${i}`} className="page-ellipsis">…</span>
          ) : (
            <button key={item} type="button" className={item === current ? 'on' : ''} onClick={() => onPage(item)}>
              {item}
            </button>
          )
        ))}
        <button type="button" className="page-nav" disabled={current >= totalPages} onClick={() => onPage(current + 1)}>
          Next <ChevronRight size={16} />
        </button>
      </div>
    </div>
  );
}

export function Field({ label, children }) {
  return (
    <label style={{ display: 'block' }}>
      <span className="label">{label}</span>
      {children}
    </label>
  );
}

export function RowActions({ onEdit, onDelete, children }) {
  return (
    <div className="row-actions" onClick={(e) => e.stopPropagation()}>
      {children}
      {onEdit && (
        <button type="button" className="btn btn-outline btn-sm" onClick={onEdit}>
          <Pencil size={14} /> Edit
        </button>
      )}
      {onDelete && (
        <button type="button" className="btn btn-danger btn-sm" onClick={onDelete}>
          <Trash2 size={14} /> Delete
        </button>
      )}
    </div>
  );
}

export function confirmDelete(label) {
  return window.confirm(`Delete this ${label}? This cannot be undone.`);
}

export function ImageField({ label, folder, path, url, onUploaded, onClear }) {
  const [err, setErr] = useState('');
  const [busy, setBusy] = useState(false);
  const preview = url || fileUrl(path);

  async function onFile(e) {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file) return;
    setBusy(true);
    setErr('');
    try {
      const r = await uploadImage(file, folder);
      onUploaded?.(r.data);
    } catch (ex) {
      setErr(ex.message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <div>
      <span className="label">{label}</span>
      <p className="muted" style={{ margin: '0 0 8px', fontSize: 13 }}>{IMAGE_HINT}</p>
      {preview ? (
        <img className="upload-preview" src={preview} alt="" />
      ) : (
        <div className="upload-preview empty">No image</div>
      )}
      <div className="btn-row" style={{ marginTop: 8 }}>
        <label className="btn btn-outline btn-sm" style={{ margin: 0 }}>
          {busy ? 'Uploading…' : (preview ? 'Replace image' : 'Choose image')}
          <input type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" hidden disabled={busy} onChange={onFile} />
        </label>
        {preview && onClear && (
          <button type="button" className="btn btn-ghost btn-sm" onClick={() => { setErr(''); onClear(); }}>Remove</button>
        )}
      </div>
      {err && <div className="alert alert-err" style={{ marginTop: 8 }}>{err}</div>}
    </div>
  );
}
