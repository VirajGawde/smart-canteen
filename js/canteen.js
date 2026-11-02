/**
 * canteen.js — bench grid -> seat modal -> confirm reservation.
 * Runs only on student/canteen.php.
 */

let selectedSeatId = null;
let selectedBenchNumber = null;
let selectedSeatNumber = null;

const overlay = document.getElementById('seatModalOverlay');
const seatGrid = document.getElementById('seatGrid');
const modalTitle = document.getElementById('seatModalTitle');
const confirmBtn = document.getElementById('confirmReservationBtn');
const summaryBox = document.getElementById('selectionSummary');
const summaryLabel = document.getElementById('selectionLabel');

document.querySelectorAll('.bench-card[data-bench-id]').forEach((card) => {
  card.addEventListener('click', () => {
    if (card.disabled) return;
    openBench(card.dataset.benchId, card.dataset.benchNumber);
  });
});

document.getElementById('refreshMapBtn')?.addEventListener('click', () => location.reload());

async function openBench(benchId, benchNumber) {
  selectedSeatId = null;
  selectedBenchNumber = benchNumber;
  modalTitle.textContent = `Bench ${benchNumber} — Select Your Seat`;
  summaryBox.style.display = 'none';
  confirmBtn.disabled = true;
  seatGrid.innerHTML = '<div class="empty-state" style="grid-column:1/-1;">Loading seats…</div>';
  openModal('seatModalOverlay');

  const data = await api(`api/get_seats.php?bench_id=${encodeURIComponent(benchId)}`);
  if (!data.ok) {
    seatGrid.innerHTML = `<div class="empty-state" style="grid-column:1/-1;">${data.message || 'Could not load this bench.'}</div>`;
    return;
  }
  renderSeats(data.seats);
}

function renderSeats(seats) {
  seatGrid.innerHTML = '';
  seats.forEach((seat) => {
    const btn = document.createElement('button');
    btn.className = `seat-btn seat-${seat.status}`;
    btn.dataset.seatId = seat.id;
    btn.dataset.seatNumber = seat.seat_number;
    btn.innerHTML = `Seat ${seat.seat_number}<span class="seat-status-text">${seat.status_label}</span>`;
    if (seat.status === 'available') {
      btn.addEventListener('click', () => selectSeat(btn, seat));
    } else {
      btn.disabled = true;
    }
    seatGrid.appendChild(btn);
  });
}

function selectSeat(btn, seat) {
  seatGrid.querySelectorAll('.seat-btn').forEach((b) => b.classList.remove('selected'));
  btn.classList.add('selected');
  selectedSeatId = seat.id;
  selectedSeatNumber = seat.seat_number;
  summaryLabel.textContent = `Bench ${selectedBenchNumber} · Seat ${selectedSeatNumber}`;
  summaryBox.style.display = 'flex';
  confirmBtn.disabled = false;
}

confirmBtn.addEventListener('click', async () => {
  if (!selectedSeatId) return;
  confirmBtn.disabled = true;
  confirmBtn.textContent = 'Confirming…';

  const data = await api('api/reserve_seat.php', {
    method: 'POST',
    body: { seat_id: selectedSeatId, csrf: csrfToken() },
  });

  if (data.ok) {
    toast('Seat reserved! Your 10-minute countdown has started.', 'success');
    window.location.href = 'my_bookings.php';
  } else {
    toast(data.message || 'Could not reserve this seat.', 'error');
    confirmBtn.textContent = 'Confirm Reservation';
    // Someone may have grabbed the seat in the meantime — refresh the panel.
    if (selectedBenchNumber) {
      const card = document.querySelector(`.bench-card[data-bench-number="${selectedBenchNumber}"]`);
      if (card) openBench(card.dataset.benchId, selectedBenchNumber);
    }
  }
});
