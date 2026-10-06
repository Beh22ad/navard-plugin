(function () {
  const startBtn = document.getElementById('navard-import-start');
  const status   = document.getElementById('navard-import-status');
  const bar      = document.getElementById('navard-import-bar');
  const count    = document.getElementById('navard-import-count');
  const logEl    = document.getElementById('navard-import-log');
  if (!startBtn) return;

  function post(action, extra = {}) {
    const body = new URLSearchParams(Object.assign({ action, nonce: window.NavardCfg.nonce }, extra));
    return fetch(window.NavardCfg.ajax, { method: 'POST', body }).then(r => r.json());
  }
  function setStatus(msg, cls) { status.textContent = msg; status.className = 'navard-status ' + (cls || ''); }
  function progress(p, t) { const pc = t ? Math.round((p / t) * 100) : 0; bar.style.width = pc + '%'; count.textContent = `پردازش شده: ${p} از ${t} (${pc}%)`; }
  function pushErr(errs) { (errs || []).forEach(e => { const d = document.createElement('div'); d.textContent = '⚠ ' + e; logEl.appendChild(d); }); }

  startBtn.addEventListener('click', async () => {
    startBtn.disabled = true; logEl.innerHTML = '';
    setStatus('در حال آماده‌سازی...');
    const s = await post('navard_import_start');
    if (!s.success) { setStatus((s.data && s.data.message) || 'خطا', 'err'); startBtn.disabled = false; return; }
    setStatus('در حال درون‌ریزی...');
    progress(0, s.data.total);

    let done = false;
    while (!done) {
      const r = await post('navard_import_batch');
      if (!r.success) { setStatus((r.data && r.data.message) || 'خطا', 'err'); break; }
      progress(r.data.processed, r.data.total);
      pushErr(r.data.errors);
      if (r.data.complete) { done = true; setStatus('درون‌ریزی کامل شد.', 'ok'); }
    }
    startBtn.disabled = false;
  });
})();