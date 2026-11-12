(() => {
    const root = document.querySelector('#receiver');
    if (!root) return;
    const $ = id => document.getElementById(id);
    const key = 'jalsaha:' + root.dataset.key;
    let state = {}, pending = false, offset = 0;
    try { state = JSON.parse(sessionStorage.getItem(key) || '{}'); } catch { /* private browsing storage may be unavailable */ }
    function save() { try { sessionStorage.setItem(key, JSON.stringify(state)); } catch {} }
    function error(message) { $('error').textContent = message; $('error').hidden = !message; }
    function render() {
        const remaining = Math.max(0, Math.ceil((Date.parse(state.expires_at || '') - (Date.now() + offset)) / 1000)) || 0;
        const active = Boolean(state.active && remaining > 0);
        $('status').textContent = active ? 'الربط فعال' : 'الربط غير فعال';
        $('status').className = 'badge ' + (active ? 'active' : 'disabled');
        $('status-box').classList.toggle('active', active);
        $('countdown').textContent = active ? `${Math.floor(remaining / 60)}:${String(remaining % 60).padStart(2, '0')}` : '—';
        $('link').disabled = pending || active;
        $('email').disabled = active;
        $('link').textContent = state.lease_id ? 'إعادة الربط' : 'ربط الإيميل';
        $('hint').textContent = active ? (state.code ? 'سيُفك الربط تلقائيًا، والكود سيبقى ظاهرًا.' : 'بانتظار وصول الكود…') : 'يمكنك إعادة الربط، أو ترك الإيميل لشخص آخر.';
        if (state.code) {
            $('code-box').hidden = false; $('copy').hidden = false; $('code').textContent = state.code;
            $('received').textContent = state.received_at ? 'وصل في ' + new Date(state.received_at).toLocaleString('ar') : '';
        }
    }
    async function request(url, options = {}) {
        const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', ...options, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, ...options.headers } });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) { const err = new Error(data.message || 'تعذر إتمام الطلب. حاول مجددًا.'); err.status = response.status; throw err; }
        return data;
    }
    function update(data) {
        offset = Date.parse(data.server_time) - Date.now();
        state = { ...state, ...data, code: data.code || state.code, received_at: data.received_at || state.received_at };
        save(); render();
    }
    $('link').addEventListener('click', async () => {
        if (pending) return;
        if (!$('email').reportValidity() || !$('email').value.trim()) return;
        pending = true; error(''); render();
        try {
            const data = await request(root.dataset.linkUrl, { method: 'POST', body: JSON.stringify({ email: $('email').value.trim() }) });
            state = { email: $('email').value.trim() }; update(data);
            if (!data.code) { $('code-box').hidden = true; $('copy').hidden = true; }
        } catch (err) { error(err.message); } finally { pending = false; render(); }
    });
    $('copy').addEventListener('click', async () => {
        try { await navigator.clipboard.writeText(state.code); $('copy').textContent = 'تم النسخ'; setTimeout(() => $('copy').textContent = 'نسخ الكود', 1500); }
        catch { error('حدد الكود وانسخه يدويًا.'); }
    });
    async function poll() {
        if (!state.lease_id || !state.lease_token || !state.active || pending) return;
        pending = true;
        try { update(await request('/leases/' + state.lease_id, { headers: { 'X-Lease-Token': state.lease_token } })); error(''); }
        catch (err) { error(err.message); if ([403, 404, 401].includes(err.status)) { state.active = false; save(); } }
        finally { pending = false; render(); }
    }
    if (state.email) $('email').value = state.email;
    render(); poll(); setInterval(poll, 3000); setInterval(render, 1000);
})();
