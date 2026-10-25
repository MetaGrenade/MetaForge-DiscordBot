import { postSignedMetaForgeRequest } from './metaforgeApi.js';

export async function postMetaForgeEvent(
  baseUrl: string,
  secret: string,
  type: string,
  payload: Record<string, unknown>,
): Promise<void> {
  await postSignedMetaForgeRequest(baseUrl, secret, '/discord/bot/events', { type, payload });
}
