import test from 'node:test';
import assert from 'node:assert/strict';
import { createHmac } from 'node:crypto';
import { signature, deliver } from '../src/delivery.js';
test('signature agrees with standard HMAC-SHA256', async () => {
    const secret = 'a'.repeat(64), body = JSON.stringify({ recipient: 'a@example.com' });
    assert.equal(await signature(body, '123', secret), createHmac('sha256', secret).update('123.' + body).digest('hex'));
});
test('failed HTTP delivery throws for queue retry', async () => {
    await assert.rejects(deliver({}, { WEBHOOK_URL: 'https://example.com/api/inbound-email', WEBHOOK_SECRET: 'a'.repeat(64) }, async () => new Response('', { status: 500 })), /500/);
});
test('delivery signs exactly the transmitted JSON and accepts success', async () => {
    const payload = { text: 'رمز التحقق 123456' }, secret = 'a'.repeat(64);
    await deliver(payload, { WEBHOOK_URL: 'https://example.com/api/inbound-email', WEBHOOK_SECRET: secret }, async (url, options) => {
        assert.equal(options.body, JSON.stringify(payload));
        assert.equal(options.headers['X-Jalsaha-Signature'], createHmac('sha256', secret).update(options.headers['X-Jalsaha-Timestamp'] + '.' + options.body).digest('hex'));
        return new Response('{}', { status: 200 });
    });
});
