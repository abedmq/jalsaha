export async function signature(body, timestamp, secret) {
    const encoder = new TextEncoder();
    const key = await crypto.subtle.importKey('raw', encoder.encode(secret), { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']);
    const digest = await crypto.subtle.sign('HMAC', key, encoder.encode(`${timestamp}.${body}`));
    return Array.from(new Uint8Array(digest), b => b.toString(16).padStart(2, '0')).join('');
}

export async function deliver(payload, env, fetcher = fetch) {
    if (!env.WEBHOOK_URL?.startsWith('https://') || !env.WEBHOOK_SECRET || env.WEBHOOK_SECRET.length < 32) throw new Error('Webhook configuration is incomplete');
    const body = JSON.stringify(payload);
    const timestamp = String(Math.floor(Date.now() / 1000));
    const response = await fetcher(env.WEBHOOK_URL, {
        method: 'POST', redirect: 'error', signal: AbortSignal.timeout(15000),
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Jalsaha-Timestamp': timestamp, 'X-Jalsaha-Signature': await signature(body, timestamp, env.WEBHOOK_SECRET) },
        body,
    });
    if (!response.ok) throw new Error(`Inbound webhook returned ${response.status}`);
}
