// IF Global Sourcing — App JS

// Modal helpers
function openModal(id) {
  document.getElementById(id).classList.add('open');
}
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
  // Reset form inside
  const form = document.querySelector('#' + id + ' form');
  if (form) form.reset();
}

// Close on overlay click
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) overlay.classList.remove('open');
    });
  });

  // Commission type toggle visibility
  const commToggles = document.querySelectorAll('input[name="commission_type"]');
  commToggles.forEach(t => {
    t.addEventListener('change', updateCommLabel);
  });
  updateCommLabel();

  // Auto-calculate debit preview
  const qtyInput = document.getElementById('qty');
  const rateInput = document.getElementById('rate');
  const debitPreview = document.getElementById('debit_preview');
  if (qtyInput && rateInput && debitPreview) {
    function calcDebit() {
      const q = parseFloat(qtyInput.value) || 0;
      const r = parseFloat(rateInput.value) || 0;
      debitPreview.textContent = 'PKR ' + (q * r).toLocaleString('en-PK', {minimumFractionDigits:2, maximumFractionDigits:2});
    }
    qtyInput.addEventListener('input', calcDebit);
    rateInput.addEventListener('input', calcDebit);
  }
});

function updateCommLabel() {
  const checked = document.querySelector('input[name="commission_type"]:checked');
  const label = document.getElementById('comm_value_label');
  if (!label || !checked) return;
  if (checked.value === 'percentage') {
    label.textContent = 'Commission %';
  } else {
    label.textContent = 'Commission per Unit (PKR)';
  }
}

// Format numbers
function fmt(n) {
  return parseFloat(n || 0).toLocaleString('en-PK', {minimumFractionDigits:2, maximumFractionDigits:2});
}

// Confirm delete
function confirmDelete(form) {
  if (confirm('Are you sure you want to delete this record?')) {
    form.submit();
  }
}

// Flash message auto-hide
setTimeout(() => {
  const alerts = document.querySelectorAll('.alert');
  alerts.forEach(a => {
    a.style.transition = 'opacity 0.5s';
    a.style.opacity = '0';
    setTimeout(() => a.remove(), 500);
  });
}, 3000);
