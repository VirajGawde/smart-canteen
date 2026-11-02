/**
 * qr-scan.js — wraps the html5-qrcode library to read the bench QR
 * code and post it to api/verify_qr.php. Falls back to a manual text
 * entry field for devices/browsers without camera access.
 */

const banner = document.getElementById('qrResultBanner');
let scanning = true;
let html5QrCode;

function showBanner(message, type) {
  banner.className = `qr-result-banner ${type}`;
  banner.textContent = message;
}

async function submitCode(qrData) {
  if (!scanning) return;
  scanning = false;

  const data = await api('api/verify_qr.php', {
    method: 'POST',
    body: { qr_data: qrData, csrf: csrfToken() },
  });

  if (data.ok) {
    showBanner(data.message || 'Seat confirmed successfully!', 'success');
    toast(data.message || 'Seat confirmed successfully!', 'success');
    if (html5QrCode) { try { await html5QrCode.stop(); } catch (e) {} }
    setTimeout(() => { window.location.href = 'my_bookings.php'; }, 1400);
  } else {
    showBanner(data.message || 'Invalid QR. Please scan the QR code of your reserved bench.', 'error');
    toast(data.message || 'Invalid QR code.', 'error');
    scanning = true; // allow retrying
  }
}

if (typeof Html5Qrcode !== 'undefined') {
  html5QrCode = new Html5Qrcode('qr-reader');
  html5QrCode
    .start(
      { facingMode: 'environment' },
      { fps: 10, qrbox: { width: 230, height: 230 } },
      (decodedText) => submitCode(decodedText),
      () => { /* per-frame "no QR found yet" — expected, ignore */ }
    )
    .catch(() => {
      showBanner('Could not access the camera — no problem, enter the code below instead.', 'error');
    });
} else {
  // The scanner library itself didn't load (e.g. no internet access to the
  // CDN it ships from) — manual entry still works since it never depended
  // on this library, just let the student know not to wait on the camera.
  showBanner('Camera scanner unavailable right now — enter the code below instead.', 'error');
}

document.getElementById('manualSubmitBtn')?.addEventListener('click', () => {
  const val = document.getElementById('manualCode').value.trim();
  if (val) submitCode(val);
});

document.getElementById('manualCode')?.addEventListener('keydown', (e) => {
  if (e.key !== 'Enter') return;
  e.preventDefault();
  const val = e.target.value.trim();
  if (val) submitCode(val);
});
