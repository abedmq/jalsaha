import PostalMime from 'postal-mime';
import { deliver } from './delivery.js';

export default {
    async email(message, env) {
        if (message.rawSize > 1048576) { message.setReject('Message exceeds 1 MB'); return; }
        const raw = await new Response(message.raw).arrayBuffer();
        if (raw.byteLength > 1048576) { message.setReject('Message exceeds 1 MB'); return; }
        const parsed = await PostalMime.parse(raw);
        const rawBytes = new Uint8Array(raw);
        const recipientBytes = new TextEncoder().encode(message.to.toLowerCase());
        const combined = new Uint8Array(rawBytes.length + recipientBytes.length);
        combined.set(rawBytes); combined.set(recipientBytes, rawBytes.length);
        const fingerprint = Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256', combined)), b => b.toString(16).padStart(2, '0')).join('');
        const text = (parsed.text || (parsed.html || '').replace(/<[^>]*>/g, ' ')).slice(0, 24000);
        const payload = {
            recipient: message.to.toLowerCase(), sender: message.from.toLowerCase(),
            subject: (parsed.subject || '(بدون عنوان)').slice(0, 500), text, fingerprint,
            source_received_at: new Date().toISOString(),
        };
        while (new TextEncoder().encode(JSON.stringify(payload)).byteLength > 120000) {
            payload.text = payload.text.slice(0, Math.floor(payload.text.length * 0.8));
        }
        // Await durable enqueue: do not acknowledge SMTP intake before the queue has accepted it.
        await env.EMAIL_QUEUE.send(payload);
    },
    async queue(batch, env) {
        for (const message of batch.messages) {
            try { await deliver(message.body, env); message.ack(); }
            catch (error) { console.error(error.message); message.retry({ delaySeconds: 30 }); }
        }
    },
};
