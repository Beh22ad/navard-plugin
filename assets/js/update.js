(function () {
  const startBtn = document.getElementById('navard-update-start');
  const status   = document.getElementById('navard-update-status');
  const bar      = document.getElementById('navard-update-bar');
  const count    = document.getElementById('navard-update-count');
  const logEl    = document.getElementById('navard-update-log');
  if (!startBtn) return;

  function post(action) {
    const body = new URLSearchParams({ action, nonce: window.NavardCfg.nonce });
    return fetch(window.NavardCfg.ajax, { method: 'POST', body }).then(r => r.json());
  }
  function setStatus(m, c) { status.textContent = m; status.className = 'navard-status ' + (c || ''); }
  function progress(p, t) { const pc = t ? Math.round((p / t) * 100) : 0; bar.style.width = pc + '%'; count.textContent = `پردازش شده: ${p} از ${t} (${pc}%)`; }
  function pushErr(e) { (e || []).forEach(x => { const d = document.createElement('div'); d.textContent = '⚠ ' + x; logEl.appendChild(d); }); }

  startBtn.addEventListener('click', async () => {
    startBtn.disabled = true; logEl.innerHTML = '';
    setStatus('در حال آماده‌سازی...');
    const s = await post('navard_update_start');
    if (!s.success) { setStatus((s.data && s.data.message) || 'خطا', 'err'); startBtn.disabled = false; return; }
    if (s.data.total === 0) { setStatus('محصولی برای بروزرسانی یافت نشد.', 'ok'); startBtn.disabled = false; return; }
    setStatus('در حال بروزرسانی...'); progress(0, s.data.total);

    let done = false;
    while (!done) {
      const r = await post('navard_update_batch');
      if (!r.success) { setStatus((r.data && r.data.message) || 'خطا', 'err'); break; }
      progress(r.data.processed, r.data.total);
      pushErr(r.data.errors);
      if (r.data.complete) { done = true; setStatus('بروزرسانی کامل شد.', 'ok'); }
    }
    startBtn.disabled = false;
  });
})();