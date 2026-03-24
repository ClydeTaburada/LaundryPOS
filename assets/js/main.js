/* ════════════════════════════════════════════════════════════════
   Lavenderia Laundry Services — Main JavaScript
   ════════════════════════════════════════════════════════════════ */

'use strict';

/* ── Sidebar toggle ─────────────────────────────────────────────── */
function toggleSidebar(open) {
  const sb      = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  if (!sb) return;
  const shouldOpen = typeof open === 'boolean' ? open : !sb.classList.contains('open');
  sb.classList.toggle('open', shouldOpen);
  overlay && overlay.classList.toggle('open', shouldOpen);
}

/* ── Global AJAX helper ─────────────────────────────────────────── */
function ajax(url, data = {}, method = 'POST') {
  const form = new FormData();
  Object.entries(data).forEach(([k, v]) => form.append(k, v));
  return fetch(url, { method, body: method === 'GET' ? null : form })
    .then(r => r.json());
}

/* ── Toast notifications ────────────────────────────────────────── */
function showToast(message, type = 'success') {
  let container = document.getElementById('toastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toastContainer';
    container.style.cssText = 'position:fixed;top:1rem;right:1rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;';
    document.body.appendChild(container);
  }

  const colors = { success: '#28C76F', danger: '#dc3545', warning: '#FF9F43', info: '#00CED1' };
  const icons  = { success: 'fa-check-circle', danger: 'fa-times-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };

  const toast = document.createElement('div');
  toast.style.cssText = `background:#fff;border-left:4px solid ${colors[type]||colors.info};border-radius:10px;padding:.75rem 1rem;box-shadow:0 6px 24px rgba(0,0,0,.15);display:flex;align-items:center;gap:.6rem;min-width:260px;max-width:360px;font-size:.85rem;animation:fadeInUp .3s ease;`;
  toast.innerHTML = `<i class="fas ${icons[type]||icons.info}" style="color:${colors[type]||colors.info};font-size:1rem;"></i><span style="flex:1">${message}</span><button onclick="this.parentElement.remove()" style="background:none;border:none;color:#999;cursor:pointer;padding:0;font-size:.9rem;">&times;</button>`;
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 4500);
}

/* ── Order status update ────────────────────────────────────────── */
function updateOrderStatus(orderId, newStatus, btn) {
  if (!orderId || !newStatus) return;
  btn && (btn.disabled = true);

  ajax(SITE_URL + '/api/orders.php', { action: 'update_status', order_id: orderId, status: newStatus })
    .then(res => {
      if (res.success) {
        showToast('Order status updated to <strong>' + newStatus + '</strong>', 'success');
        setTimeout(() => location.reload(), 900);
      } else {
        showToast(res.message || 'Failed to update status', 'danger');
        btn && (btn.disabled = false);
      }
    })
    .catch(() => { showToast('Network error', 'danger'); btn && (btn.disabled = false); });
}

/* ── Delete confirmation helper ─────────────────────────────────── */
function confirmDelete(url, name) {
  if (!confirm(`Delete "${name}"?\n\nThis action cannot be undone.`)) return;
  window.location.href = url;
}

function ajaxDelete(apiUrl, data, successCb) {
  if (!confirm('Delete this record? This cannot be undone.')) return;
  ajax(apiUrl, { ...data, action: 'delete' })
    .then(res => {
      if (res.success) { showToast('Record deleted.', 'success'); successCb && successCb(); }
      else showToast(res.message || 'Delete failed.', 'danger');
    });
}

/* ── Table live search ───────────────────────────────────────────── */
function initTableSearch(inputId, tableId) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;

  input.addEventListener('input', function () {
    const q = this.value.toLowerCase();
    table.querySelectorAll('tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

/* ── Currency formatter ─────────────────────────────────────────── */
function formatCurrency(amount) {
  return '₱' + parseFloat(amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/* ── Order total calculator ─────────────────────────────────────── */
function recalcOrderTotal() {
  const pricingType = document.querySelector('input[name="pricing_type"]:checked')?.value || 'per_kilo';
  const weight      = parseFloat(document.getElementById('weight')?.value || 0);
  const priceUnit   = parseFloat(document.getElementById('price_per_unit')?.value || 0);
  let total         = 0;

  if (pricingType === 'per_kilo') {
    total = weight * priceUnit;
  } else {
    // per_item: sum order_items
    document.querySelectorAll('.item-subtotal').forEach(el => {
      total += parseFloat(el.dataset.subtotal || 0);
    });
  }

  const totalEl = document.getElementById('totalAmount');
  if (totalEl) totalEl.textContent = formatCurrency(total);

  const hiddenEl = document.getElementById('totalAmountHidden');
  if (hiddenEl) hiddenEl.value = total.toFixed(2);
}

/* ── Service price lookup ───────────────────────────────────────── */
const SERVICE_PRICES = {
  wash_fold: { per_kilo: 55, per_item: 0 },
  dry_clean: { per_kilo: 130, per_item: 120 },
  ironing:   { per_kilo: 0,  per_item: 40 },
};

function onServiceChange(val) {
  const pricingType = document.querySelector('input[name="pricing_type"]:checked')?.value || 'per_kilo';
  const prices = SERVICE_PRICES[val] || {};
  const priceInput = document.getElementById('price_per_unit');
  if (priceInput && prices[pricingType] !== undefined) {
    priceInput.value = prices[pricingType];
    recalcOrderTotal();
  }
}

/* ── Due date auto-calculate ────────────────────────────────────── */
function calcDueDate(serviceType) {
  const days = { wash_fold: 1, dry_clean: 3, ironing: 1 };
  const d    = new Date();
  d.setDate(d.getDate() + (days[serviceType] || 1));
  // format to datetime-local value
  const pad = n => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

/* ── Barcode generator ───────────────────────────────────────────── */
function generateBarcode(value, elementId) {
  const el = document.getElementById(elementId);
  if (!el || !value || typeof JsBarcode === 'undefined') return;
  try {
    const containerWidth = (el.parentElement?.clientWidth || 260) - 16;
    const barWidth = Math.max(1, Math.floor(containerWidth / value.length / 1.5));
    JsBarcode(el, value, { format: 'CODE128', width: barWidth, height: 50, displayValue: false, margin: 4 });
    el.style.maxWidth = '100%';
  } catch (e) { console.warn('Barcode error:', e); }
}

/* ── QR Code generator ───────────────────────────────────────────── */
function generateQR(value, elementId) {
  const el = document.getElementById(elementId);
  if (!el || !value || typeof QRCode === 'undefined') return;
  el.innerHTML = '';
  new QRCode(el, { text: value, width: 100, height: 100, colorDark: '#8A2BE2', colorLight: '#fff', correctLevel: QRCode.CorrectLevel.M });
}

/* ── Print receipt ───────────────────────────────────────────────── */
function printReceipt(orderId) {
  window.open(SITE_URL + '/modules/payments/receipt.php?id=' + orderId, '_blank', 'width=500,height=700,scrollbars=yes');
}

/* ── Branch filter (owner/admin) ─────────────────────────────────── */
function applyBranchFilter(branchId) {
  const url = new URL(window.location.href);
  url.searchParams.set('branch_id', branchId);
  window.location.href = url.toString();
}

/* ── Chart defaults ──────────────────────────────────────────────── */
function setChartDefaults() {
  if (typeof Chart === 'undefined') return;
  Chart.defaults.font.family  = "'Poppins', 'Segoe UI', sans-serif";
  Chart.defaults.font.size    = 12;
  Chart.defaults.color        = '#6c757d';
  Chart.defaults.plugins.legend.position = 'bottom';
}

/* ── Build Sales Line Chart ──────────────────────────────────────── */
function buildSalesChart(canvasId, labels, data) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;
  return new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Sales (₱)',
        data,
        borderColor: '#8A2BE2',
        backgroundColor: 'rgba(138,43,226,.12)',
        fill: true,
        tension: .45,
        pointBackgroundColor: '#8A2BE2',
        pointRadius: 4,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, ticks: { callback: v => '₱' + v.toLocaleString() }, grid: { color: '#F3E5F5' } },
        x: { grid: { color: '#F3E5F5' } }
      }
    }
  });
}

/* ── Build Payment Doughnut Chart ────────────────────────────────── */
function buildPaymentChart(canvasId, cash, gcash, unpaid) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;
  return new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Cash', 'GCash', 'Unpaid'],
      datasets: [{
        data: [cash, gcash, unpaid],
        backgroundColor: ['#28C76F', '#0080FF', '#dc3545'],
        borderWidth: 2,
        borderColor: '#fff',
      }]
    },
    options: {
      responsive: true,
      cutout: '68%',
      plugins: { legend: { position: 'bottom' } }
    }
  });
}

/* ── Build Branch Bar Chart ──────────────────────────────────────── */
function buildBranchChart(canvasId, labels, data) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;
  return new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Orders',
        data,
        backgroundColor: 'rgba(138,43,226,.7)',
        borderColor: '#8A2BE2',
        borderWidth: 1,
        borderRadius: 6,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#F3E5F5' } },
        x: { grid: { display: false } }
      }
    }
  });
}

/* ── Build Service Pie Chart ─────────────────────────────────────── */
function buildServiceChart(canvasId, labels, data) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;
  return new Chart(ctx, {
    type: 'pie',
    data: {
      labels,
      datasets: [{
        data,
        backgroundColor: ['#8A2BE2', '#00CED1', '#FF9F43'],
        borderWidth: 2,
        borderColor: '#fff',
      }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
  });
}

/* ── Init on DOM ready ───────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  setChartDefaults();

  // Auto-init table searches
  initTableSearch('searchInput', 'mainTable');

  // Bootstrap tooltips
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    new bootstrap.Tooltip(el, { trigger: 'hover' });
  });

  // Auto-dismiss alerts
  document.querySelectorAll('.alert-dismissible.auto-dismiss').forEach(el => {
    setTimeout(() => el.remove(), 4000);
  });
});
