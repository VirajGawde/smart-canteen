/**
 * complaints.js — two independent features on the same page:
 *  1) responding to a pending bench-feedback request
 *  2) submitting a new complaint (multipart, for the optional image)
 */

// ---------------- Bench feedback response ----------------
document.querySelectorAll('[data-response-group]').forEach((group) => {
  group.querySelectorAll('button').forEach((btn) => {
    btn.addEventListener('click', () => {
      group.querySelectorAll('button').forEach((b) => b.classList.remove('selected'));
      btn.classList.add('selected');
      group.dataset.selected = btn.dataset.value;
    });
  });
});

document.querySelectorAll('.submit-feedback-btn').forEach((btn) => {
  btn.addEventListener('click', async () => {
    const card = btn.closest('.feedback-request-card');
    const group = card.querySelector('[data-response-group]');
    const response = group.dataset.selected;
    if (!response) { toast('Please choose Yes, No, or Not sure.', 'error'); return; }

    btn.disabled = true;
    btn.textContent = 'Submitting…';
    const comments = card.querySelector('.feedback-comment').value.trim();

    const data = await api('api/submit_bench_feedback.php', {
      method: 'POST',
      body: { feedback_id: card.dataset.feedbackId, response, comments, csrf: csrfToken() },
    });

    if (data.ok) {
      toast('Thanks — your response was recorded.', 'success');
      card.style.opacity = '0.5';
      card.style.pointerEvents = 'none';
      btn.textContent = 'Submitted';
    } else {
      toast(data.message || 'Could not submit your response.', 'error');
      btn.disabled = false;
      btn.textContent = 'Submit Response';
    }
  });
});

// ---------------- New complaint form ----------------
const uploadDrop = document.getElementById('uploadDrop');
const imageInput = document.getElementById('imageInput');
const imagePreview = document.getElementById('imagePreview');

uploadDrop?.addEventListener('click', () => imageInput.click());
imageInput?.addEventListener('change', () => {
  const file = imageInput.files[0];
  if (!file) return;
  if (file.size > 5 * 1024 * 1024) {
    toast('Image must be under 5MB.', 'error');
    imageInput.value = '';
    return;
  }
  imagePreview.src = URL.createObjectURL(file);
  imagePreview.style.display = 'block';
});

document.getElementById('complaintForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const submitBtn = document.getElementById('submitComplaintBtn');
  submitBtn.disabled = true;
  submitBtn.textContent = 'Submitting…';

  const formData = new FormData(e.target);
  formData.append('csrf', csrfToken());

  const data = await api('api/submit_complaint.php', { method: 'POST', body: formData, isForm: true });

  if (data.ok) {
    toast('Complaint submitted successfully.', 'success');
    setTimeout(() => location.reload(), 900);
  } else {
    toast(data.message || 'Could not submit complaint.', 'error');
    submitBtn.disabled = false;
    submitBtn.textContent = 'Submit Complaint';
  }
});
