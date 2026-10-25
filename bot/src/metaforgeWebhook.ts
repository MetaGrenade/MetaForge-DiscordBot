import crypto from 'node:crypto';

export async function postMetaForgeEvent(
  baseUrl: string,
  secret: string,
  type: string,
  payload: Record<string, unknown>,
): Promise<void> {
  const body = JSON.stringify({ type, payload });
  const timestamp = Math.floor(Date.now() / 1000).toString();
  const signature = crypto.createHmac('sha256', secret).update(`${timestamp}.${body}`).digest('hex');

  const response = await fetch(`${baseUrl.replace(/\/$/, '')}/discord/bot/events`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Discord-Bot-Timestamp': timestamp,
      'X-Discord-Bot-Signature': signature,
    },
    body,
  });

  if (!response.ok) {
    const text = await response.text();
    throw new Error(`MetaForge webhook failed (${response.status}): ${text}`);
  }
}
