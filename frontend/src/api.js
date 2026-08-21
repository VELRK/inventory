// API base that matches server CodeIgniter location under `plots`
const API = '/plots/index.php/api';

export function getToken() {
  return localStorage.getItem('syncr_token') || '';
}
export function setSession(token, user) {
  localStorage.setItem('syncr_token', token);
  localStorage.setItem('syncr_user', JSON.stringify(user));
}
export function updateSessionUser(user) {
  localStorage.setItem('syncr_user', JSON.stringify(user));
}
export function getUser() {
  try { return JSON.parse(localStorage.getItem('syncr_user') || 'null'); } catch { return null; }
}
export function clearSession() {
  localStorage.removeItem('syncr_token');
  localStorage.removeItem('syncr_user');
}

/** Check if current (or given) user has a permission key from Access control. */
export function can(permissionKey, user = getUser()) {
  if (!user) return false;
  if (user.role === 'promoter_admin' && (permissionKey === 'nav.access' || permissionKey === 'access.manage')) {
    return true;
  }
  const list = user.permissions;
  if (!Array.isArray(list)) {
    // Legacy session before permissions existed — keep old role defaults.
    if (user.role === 'promoter_admin') return true;
    if (user.role === 'marketing_team_admin') {
      return [
        'nav.dashboard', 'nav.projects', 'nav.inventory', 'nav.requests', 'nav.bookings', 'nav.users',
        'inventory.edit', 'users.manage', 'bookings.manage',
      ].includes(permissionKey);
    }
    return ['nav.dashboard', 'nav.projects', 'nav.inventory', 'nav.requests', 'nav.users'].includes(permissionKey);
  }
  return list.includes(permissionKey);
}

export async function api(path, { method = 'GET', body, headers } = {}) {
  const opts = {
    method,
    headers: {
      Accept: 'application/json',
      ...(body ? { 'Content-Type': 'application/json' } : {}),
      ...(getToken() ? { Authorization: `Bearer ${getToken()}` } : {}),
      ...headers,
    },
  };
  if (body !== undefined) opts.body = JSON.stringify(body);
  let res;
  try {
    res = await fetch(`${API}${path}`, opts);
  } catch (e) {
    const err = new Error('Cannot reach the API. Keep the frontend running (npm run dev) and Apache on port 8080 with MySQL started.');
    err.status = 0;
    err.payload = { success: false, error: { code: 'NETWORK', message: err.message } };
    throw err;
  }
  const text = await res.text();
  let json = null;
  try { json = text ? JSON.parse(text) : null; } catch { json = { success: false, error: { code: 'PARSE_ERROR', message: text.slice(0, 300) } }; }
  if (!res.ok || json?.success === false) {
    const err = new Error(json?.error?.message || `Request failed (${res.status})`);
    err.status = res.status;
    err.payload = json;
    throw err;
  }
  return json;
}

export const fmt = (n) => '₹' + Number(n || 0).toLocaleString('en-IN');

export const IMAGE_MAX_BYTES = 4 * 1024 * 1024;
export const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
export const IMAGE_HINT = 'JPG, PNG or WEBP · max 4 MB';

export function validateImageFile(file) {
  if (!file) return 'Please choose an image to upload.';
  const name = (file.name || '').toLowerCase();
  const extOk = ['.jpg', '.jpeg', '.png', '.webp'].some((ext) => name.endsWith(ext));
  const typeOk = !file.type || IMAGE_TYPES.includes(file.type);
  if (!extOk || !typeOk) return 'Invalid file type. Upload a JPG, PNG, or WEBP image.';
  if (file.size <= 0) return 'The selected file is empty.';
  if (file.size > IMAGE_MAX_BYTES) {
    const mb = (file.size / (1024 * 1024)).toFixed(1);
    return `The file is ${mb} MB. Maximum size is 4 MB. Compress the image and try again.`;
  }
  return '';
}

export function fileUrl(path) {
  if (!path) return '';
  if (/^https?:\/\//i.test(path)) return path;
  return `/plots/${String(path).replace(/^\//, '')}`;
}

export async function uploadImage(file, folder) {
  const msg = validateImageFile(file);
  if (msg) {
    const err = new Error(msg);
    err.status = 422;
    throw err;
  }
  const fd = new FormData();
  fd.append('file', file);
  fd.append('folder', folder);
  let res;
  try {
    res = await fetch(`${API}/upload`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        ...(getToken() ? { Authorization: `Bearer ${getToken()}` } : {}),
      },
      body: fd,
    });
  } catch {
    const err = new Error('Cannot reach the API. Keep Apache on port 8080 and try again.');
    err.status = 0;
    throw err;
  }
  const text = await res.text();
  let json = null;
  try { json = text ? JSON.parse(text) : null; } catch { json = { success: false, error: { code: 'PARSE_ERROR', message: text.slice(0, 300) } }; }
  if (!res.ok || json?.success === false) {
    const details = json?.error?.details;
    const fieldMsg = details && typeof details === 'object' ? Object.values(details)[0] : null;
    const err = new Error(fieldMsg || json?.error?.message || `Upload failed (${res.status})`);
    err.status = res.status;
    err.payload = json;
    throw err;
  }
  return json;
}
