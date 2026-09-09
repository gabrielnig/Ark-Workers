const BASE_URL = import.meta.env.VITE_API_BASE_URL;

/**
 * Reads the XSRF-TOKEN cookie Laravel sets. The cookie value is
 * encrypted but that does not matter here, we only echo it back as a
 * header exactly as received, the server decrypts and compares it.
 */
function getXsrfTokenFromCookie() {
  const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
  return match ? decodeURIComponent(match[1]) : null;
}

/**
 * Call once before the first write request of any kind (register,
 * login, anything else). Sets the XSRF-TOKEN and session cookies
 * needed for the stateful auth flow in SECURITY.md 6.3. Capacitor's
 * Android build never calls this, its requests come from a
 * different origin and use a bearer token instead, see
 * AuthController::login on the backend.
 */
export async function primeCsrfCookie() {
  await fetch(`${BASE_URL}/sanctum/csrf-cookie`, {
    credentials: 'include',
  });
}

export async function apiFetch(path, options = {}) {
  const isWrite = options.method && options.method !== 'GET';

  const response = await fetch(`${BASE_URL}${path}`, {
    ...options,
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
      ...(isWrite ? { 'X-XSRF-TOKEN': getXsrfTokenFromCookie() } : {}),
      ...options.headers,
    },
  });

  if (!response.ok) {
    const body = await response.json().catch(() => null);
    const error = new Error(body?.message || `Request failed: ${response.status}`);
    error.status = response.status;
    error.body = body;
    throw error;
  }

  if (response.status === 204) {
    return null;
  }

  return response.json();
}
