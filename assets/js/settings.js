(function () {
  const btn = document.getElementById('navard-check-key');
  const st  = document.getElementById('navard-key-status');
  const keyEl = document.getElementById('navard-api-key');

  if (btn && st && keyEl) {
    btn.addEventListener('click', async () => {
      st.textContent = 'در حال بررسی...';
      st.className = 'navard-status';

      const epEl = document.querySelector('input[name="endpoint"]:checked');
      const body = new URLSearchParams({
        action: 'navard_check_key',
        nonce: window.NavardCfg.nonce,
        key: keyEl.value,
        endpoint: epEl ? epEl.value : ''
      });

      try {
        const r = await fetch(window.NavardCfg.ajax, { method: 'POST', body });
        const j = await r.json();
        st.textContent = (j.data && j.data.message) || 'ناموفق';
        st.className = 'navard-status ' + (j.success ? 'ok' : 'err');
      } catch (e) {
        st.textContent = 'خطای شبکه';
        st.className = 'navard-status err';
      }
    });
  }

  const modSelect = document.getElementById('navard-mod-type');
  const modWrap   = document.querySelector('.navard-mod-value-wrap');
  const helpPct   = document.querySelector('.navard-help-percent');
  const helpFix   = document.querySelector('.navard-help-fixed');

  function syncMod() {
    const v = modSelect ? modSelect.value : 'none';

    if (modWrap) {
      modWrap.style.display = (v === 'percent' || v === 'fixed') ? '' : 'none';
    }
    if (helpPct) {
      helpPct.style.display = (v === 'percent') ? '' : 'none';
    }
    if (helpFix) {
      helpFix.style.display = (v === 'fixed') ? '' : 'none';
    }
  }

  if (modSelect) {
    modSelect.addEventListener('change', syncMod);
    syncMod();
  }

  const roundChk = document.getElementById('navard-round-enabled');
  const roundWrap = document.querySelector('.navard-round-unit-wrap');

  function syncRound() {
    if (roundWrap) {
      roundWrap.style.display = (roundChk && roundChk.checked) ? '' : 'none';
    }
  }

  if (roundChk) {
    roundChk.addEventListener('change', syncRound);
    syncRound();
  }
})();