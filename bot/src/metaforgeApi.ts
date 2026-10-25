import crypto from 'node:crypto';

function signedHeaders(secret: string, body: string): Record<string, string> {
  const timestamp = Math.floor(Date.now() / 1000).toString();
  const signature = crypto.createHmac('sha256', secret).update(`${timestamp}.${body}`).digest('hex');

  return {
    'Content-Type': 'application/json',
    'X-Discord-Bot-Timestamp': timestamp,
    'X-Discord-Bot-Signature': signature,
  };
}

export async function postSignedMetaForgeRequest<T>(
  baseUrl: string,
  secret: string,
  path: string,
  payload: Record<string, unknown>,
): Promise<T> {
  const body = JSON.stringify(payload);
  const url = `${baseUrl.replace(/\/$/, '')}${path.startsWith('/') ? path : `/${path}`}`;

  const response = await fetch(url, {
    method: 'POST',
    headers: signedHeaders(secret, body),
    body,
  });

  if (!response.ok) {
    const text = await response.text();
    throw new Error(`MetaForge API failed (${response.status}): ${text}`);
  }

  return (await response.json()) as T;
}
