/**
 * admin.js — shared across admin pages. Each block guards on the
 * presence of its own DOM elements so this one file works whether
 * it's loaded on benches.php, analytics.php, etc.
 */

// ---------------- QR modal (admin/benches.php) ----------------
const qrCanvas = document.getElementById('qrCanvas');
if (qrCanvas) {
  const qrTokenInput = document.getElementById('qrTokenInput');
  const qrBenchIdInput = document.getElementById('qrBenchIdInput');

  function renderQrPreview(value) {
    if (!value) return;
    // The picture is drawn by a small library loaded from a CDN. If that is
    // blocked (campus Wi-Fi, no internet) skip the picture rather than throw:
    // the code itself must still open, edit and save — that is what students
    // actually type in or scan against.
    if (typeof QRCode === 'undefined') {
      qrCanvas.style.display = 'none';
      return;
    }
    qrCanvas.style.display = '';
    QRCode.toCanvas(qrCanvas, value, { width: 220, margin: 1 }, (err) => {
      if (err) toast('Could not render QR code (are you online? it loads from a CDN).', 'error');
    });
  }

  function randomQrToken() {
    const bytes = crypto.getRandomValues(new Uint8Array(8));
    return 'SCB-' + Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
  }

  document.querySelectorAll('.view-qr-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      const bench = btn.dataset.bench;
      const token = btn.dataset.token;
      document.getElementById('qrModalTitle').textContent = `Bench ${bench} — QR Code`;
      qrBenchIdInput.value = btn.dataset.benchId;
      qrTokenInput.value = token;
      renderQrPreview(token);
      openModal('qrModalOverlay');
      if (typeof QRCode === 'undefined') {
        toast('The QR picture needs internet to draw — you can still edit and save the code.', 'info');
      }
    });
  });

  // Live preview as the admin edits the code, so the canvas (and the
  // downloaded PNG) always matches what's about to be saved.
  qrTokenInput?.addEventListener('input', () => renderQrPreview(qrTokenInput.value.trim()));

  document.getElementById('regenQrBtn')?.addEventListener('click', () => {
    qrTokenInput.value = randomQrToken();
    renderQrPreview(qrTokenInput.value);
  });

  document.getElementById('downloadQrBtn')?.addEventListener('click', () => {
    if (typeof QRCode === 'undefined') {
      toast('Download needs the QR picture, which needs internet.', 'error');
      return;
    }
    const link = document.createElement('a');
    link.download = `${document.getElementById('qrModalTitle').textContent.replace(/\s+/g, '_')}.png`;
    link.href = qrCanvas.toDataURL('image/png');
    link.click();
  });
}

// ---------------- Find-a-bench box (admin/benches.php) ----------------
// 84 benches make a long list, so the admin can narrow it by bench number,
// area, open/closed, or "occupied"/"reserved". Every typed word must match
// the START of some word on the row ("4" finds 4, 40-49; "front l" finds
// Front Left) — so "available" never matches "unavailable".
const benchFilter = document.getElementById('benchFilter');
if (benchFilter) {
  const rows = Array.from(document.querySelectorAll('.bench-manage-row[data-search]'));
  const countEl = document.getElementById('benchCount');
  const emptyEl = document.getElementById('benchFilterEmpty');
  const idleText = countEl ? countEl.textContent : '';

  const applyBenchFilter = () => {
    const typed = benchFilter.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
    let shown = 0;
    rows.forEach((row) => {
      const words = row.dataset.search.split(/\s+/);
      const hit = typed.every((t) => words.some((w) => w.startsWith(t)));
      row.style.display = hit ? '' : 'none';
      if (hit) shown++;
    });
    if (countEl) countEl.textContent = typed.length ? `Showing ${shown} of ${rows.length} benches` : idleText;
    if (emptyEl) emptyEl.style.display = shown === 0 ? '' : 'none';
  };

  benchFilter.addEventListener('input', applyBenchFilter);
}

// ---------------- Analytics charts (admin/analytics.php) ----------------
if (typeof analyticsData !== 'undefined' && typeof Chart !== 'undefined') {
  const navy = '#0F2A4A', red = '#9C231F', slate = '#E4E9F1';
  Chart.defaults.font.family = 'Inter, sans-serif';
  Chart.defaults.color = '#4B5768';

  new Chart(document.getElementById('hourChart'), {
    type: 'bar',
    data: {
      labels: Array.from({ length: 24 }, (_, i) => `${i}:00`),
      datasets: [{ data: analyticsData.hours, backgroundColor: red, borderRadius: 4, maxBarThickness: 18 }],
    },
    options: { plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { grid: { color: slate }, beginAtZero: true, ticks: { precision: 0 } } } },
  });

  new Chart(document.getElementById('benchChart'), {
    type: 'bar',
    data: { labels: analyticsData.benchLabels, datasets: [{ data: analyticsData.benchCounts, backgroundColor: navy, borderRadius: 6 }] },
    options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { grid: { color: slate }, beginAtZero: true, ticks: { precision: 0 } }, y: { grid: { display: false } } } },
  });

  new Chart(document.getElementById('noShowChart'), {
    type: 'line',
    data: { labels: analyticsData.noShowLabels, datasets: [{ data: analyticsData.noShowCounts, borderColor: '#E5484D', backgroundColor: 'rgba(229,72,77,.12)', fill: true, tension: 0.35, pointRadius: 4 }] },
    options: { plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { grid: { color: slate }, beginAtZero: true, ticks: { precision: 0 } } } },
  });

  new Chart(document.getElementById('complaintChart'), {
    type: 'doughnut',
    data: {
      labels: analyticsData.complaintLabels,
      datasets: [{ data: analyticsData.complaintCounts, backgroundColor: ['#9C231F', '#E8A33D', '#18A971', '#E5484D', '#8791A1'] }],
    },
    options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 14, font: { size: 11.5 } } } } },
  });
}
