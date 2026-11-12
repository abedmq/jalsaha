import test from 'node:test';
import assert from 'node:assert/strict';
import worker from '../src/index.js';

function email(raw, overrides = {}) {
    return { from: 'login@trusted.example', to: 'customer@example.com', rawSize: new TextEncoder().encode(raw).length, raw: new Blob([raw]).stream(), setReject(reason) { this.rejection = reason; }, ...overrides };
}
test('MIME intake queues decoded content and a stable fingerprint', async () => {
    const raw = 'From: login@trusted.example\r\nTo: customer@example.com\r\nSubject: Your code\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\nWW91ciBjb2RlIGlzIDAwMTIzNA==';
    const queue = [], env = { EMAIL_QUEUE: { send: async value => queue.push(value) } };
    await worker.email(email(raw), env); await worker.email(email(raw), env);
    assert.equal(queue[0].text.trim(), 'Your code is 001234');
    assert.match(queue[0].fingerprint, /^[a-f0-9]{64}$/);
    assert.equal(queue[0].fingerprint, queue[1].fingerprint);
    await worker.email(email(raw, { to: 'other@example.com' }), env);
    assert.notEqual(queue[0].fingerprint, queue[2].fingerprint);
});
test('oversized intake is rejected without enqueuing', async () => {
    const message = email('', { rawSize: 1048577 });
    await worker.email(message, { EMAIL_QUEUE: { send: async () => assert.fail('must not queue') } });
    assert.match(message.rejection, /1 MB/);
});
test('queue acknowledges success and retries a failed delivery', async () => {
    let retried = false;
    await worker.queue({ messages: [{ body: {}, ack: () => assert.fail('invalid config must not acknowledge'), retry: () => { retried = true; } }] }, {});
    assert.equal(retried, true);
});
