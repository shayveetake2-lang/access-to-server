import { getBaseUrl } from '../../utils/api';

const ADMIN_TOKEN = '18e499b984c75ad09e233f6d8fe0228d';

export async function adminPost(action, payload = {}) {
  const url = `${getBaseUrl()}/api/manage_content.php`;
  const res = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Admin-Token': ADMIN_TOKEN,
    },
    body: JSON.stringify({ action, ...payload }),
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({ error: `HTTP ${res.status}` }));
    throw new Error(err.error || `HTTP ${res.status}`);
  }
  const data = await res.json();
  if (!data.ok) throw new Error(data.error || 'Unknown error');
  return data;
}

