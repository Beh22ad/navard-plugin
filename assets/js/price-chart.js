(function () {
  if (typeof window.NavardChartCfg === 'undefined') return;

  const cfg = window.NavardChartCfg;

  function openModal() {
    const m = document.getElementById('navard-chart-modal');
    if (m) {
      m.classList.add('is-open');
      m.setAttribute('aria-hidden', 'false');
    }
  }

  function closeModal() {
    const m = document.getElementById('navard-chart-modal');
    if (m) {
      m.classList.remove('is-open');
      m.setAttribute('aria-hidden', 'true');
    }
  }

  function fmt(n) {
    return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  // "1405/07/06" -> "07/06" ; anything else is returned unchanged.
  function shortDate(s) {
    const str = String(s);
    const m = str.match(/^\s*\d{4}\/(\d{1,2}\/\d{1,2})\s*$/);
    return m ? m[1] : str;
  }

  function showMessage(html) {
    const body = document.getElementById('navard-chart-modal-body');
    if (body) body.innerHTML = '<div class="navard-chart-empty">' + html + '</div>';
    openModal();
  }

  function niceCeil(v) {
    if (v <= 0) return 0;
    const mag = Math.pow(10, Math.floor(Math.log10(v)));
    return Math.ceil(v / mag) * mag;
  }

  function niceStep(range, ticks) {
    const rough = range / Math.max(1, ticks);
    const mag   = Math.pow(10, Math.floor(Math.log10(rough)));
    const norm  = rough / mag;
    let mult;
    if (norm <= 1)      mult = 1;
    else if (norm <= 2) mult = 2;
    else if (norm <= 5) mult = 5;
    else                mult = 10;
    return mult * mag;
  }

  function buildSVG(labels, values) {
    const W = 700, H = 400;
    const padL = 80, padR = 30, padT = 40, padB = 90;
    const innerW = W - padL - padR;
    const innerH = H - padT - padB;

    const n = values.length;

    const sidePadFrac = 0.12;
    const xStart = padL + innerW * sidePadFrac;
    const xEnd   = padL + innerW * (1 - sidePadFrac);
    const xSpan  = xEnd - xStart;
    const stepX  = n > 1 ? xSpan / (n - 1) : 0;

    const rawMin = Math.min.apply(null, values);
    const rawMax = Math.max.apply(null, values);
    const rawRange = (rawMax - rawMin) || Math.max(1, rawMax * 0.1);

    const step = niceStep(rawRange, 5);
    const yBottom = Math.max(0, Math.floor((rawMin - rawRange * 0.1) / step) * step);
    const yTop    = niceCeil(rawMax + rawRange * 0.1);
    const ySpan   = (yTop - yBottom) || 1;

    const points = values.map(function (v, i) {
      const x = n > 1 ? xStart + stepX * i : (xStart + xEnd) / 2;
      const y = padT + innerH - ((v - yBottom) / ySpan) * innerH;
      return { x: x, y: y, v: v };
    });

    const poly = points.map(function (p) { return p.x + ',' + p.y; }).join( ' ');

    let gridY = '';
    const tickCount = Math.max(2, Math.round(ySpan / step));
    for (let i = 0; i <= tickCount; i++) {
      const value = yBottom + step * i;
      if (value > yTop) break;
      const y = padT + innerH - ((value - yBottom) / ySpan) * innerH;
      gridY += '<line x1="' + padL + '" y1="' + y + '" x2="' + (padL + innerW) + '" y2="' + y + '" stroke="#eee" stroke-width="1"/>';
      gridY += '<text x="' + (padL - 8) + '" y="' + (y + 4) + '" text-anchor="end" font-size="11" fill="#888">' + fmt(value) + '</text>';
    }

    // X axis labels — horizontal, no rotation, year trimmed.
    let labelsSvg = '';
    const labelStep = Math.max(1, Math.ceil(n / 8));
    points.forEach(function (p, i) {
      if (i % labelStep !== 0 && i !== n - 1) return;
      labelsSvg += '<text x="' + p.x + '" y="' + (padT + innerH + 22) + '" text-anchor="middle" font-size="11" fill="#666">' + esc(shortDate(labels[i])) + '</text>';
    });

    // Axis titles
    const xTitleY = padT + innerH + 60;
    const axisX = '<text x="' + (padL + innerW / 2) + '" y="' + xTitleY + '" text-anchor="middle" font-size="12" fill="#555" font-weight="600">' + esc(cfg.i18n.xaxis) + '</text>';

    const yTitleX = 20;
    const yTitleY = padT + innerH / 2;
    const axisY = '<text x="' + yTitleX + '" y="' + yTitleY + '" text-anchor="middle" font-size="12" fill="#555" font-weight="600" transform="rotate(-90 ' + yTitleX + ' ' + yTitleY + ')">' + esc(cfg.i18n.yaxis) + '</text>';

    let dots = '';
    points.forEach(function (p, i) {
      dots += '<circle cx="' + p.x + '" cy="' + p.y + '" r="4" fill="#c62828" stroke="#fff" stroke-width="2"/>'
           +  '<title>' + esc(labels[i]) + ': ' + fmt(p.v) + '</title>';
    });

    return ''
      + '<svg viewBox="0 0 ' + W + ' ' + H + '" width="100%" height="auto" xmlns="http://www.w3.org/2000/svg">'
      + axisY
      + gridY
      + '<polyline fill="none" stroke="#c62828" stroke-width="2" points="' + poly + '"/>'
      + dots
      + labelsSvg
      + axisX
      + '</svg>';
  }

  function load(productId) {
    const body = document.getElementById('navard-chart-modal-body');
    if (body) body.innerHTML = '<div class="navard-chart-empty">در حال بارگذاری…</div>';
    openModal();

    const form = new URLSearchParams();
    form.append('action', 'navard_price_history');
    form.append('nonce', cfg.nonce);
    form.append('product_id', productId);

    fetch(cfg.ajax, { method: 'POST', body: form })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j || !j.success || !j.data) {
          showMessage(cfg.i18n.error);
          return;
        }
        if (j.data.empty) {
          showMessage(cfg.i18n.empty);
          return;
        }
        const svg = buildSVG(j.data.labels, j.data.values);
        if (body) body.innerHTML = svg;
      })
      .catch(function () {
        showMessage(cfg.i18n.error);
      });
  }

  document.addEventListener('click', function (e) {
    const chart = e.target.closest && e.target.closest('.navard-sc-chart');
    if (chart) {
      const pid = parseInt(chart.getAttribute('data-product-id'), 10);
      if (pid > 0) {
        load(pid);
      }
      return;
    }
    const close = e.target.closest && e.target.closest('[data-navard-close]');
    if (close) {
      closeModal();
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' || e.keyCode === 27) {
      closeModal();
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') {
      const el = document.activeElement;
      if (el && el.classList && el.classList.contains('navard-sc-chart')) {
        e.preventDefault();
        const pid = parseInt(el.getAttribute('data-product-id'), 10);
        if (pid > 0) load(pid);
      }
    }
  });
})();