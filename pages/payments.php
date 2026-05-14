<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();

$pageTitle = 'Payments';
$activePage = 'payments';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $date         = $_POST['payment_date'];
        $supplier_id  = $_POST['supplier_id'] ?: null;
        $customer_id  = $_POST['customer_id'] ?: null;
        
        // Validate that at least one party is selected
        if (!$customer_id && !$supplier_id) {
            $msg = 'error:Please select at least one party (Customer or Supplier).';
        } else {
            // Determine payer_type based on which party is selected
            if ($customer_id && $supplier_id) {
                $payer_type = 'customer'; // Default to customer if both selected
            } elseif ($customer_id) {
                $payer_type = 'customer';
            } else {
                $payer_type = 'supplier';
            }
            
            if (empty($msg)) {
                $payment_type = $_POST['payment_type'];
                $amount       = (float)$_POST['amount'];
                $note         = strip_tags(trim($_POST['note']));
                $receipt_number = trim($_POST['receipt_number'] ?? '');
                $contract_id  = !empty($_POST['contract_id']) ? (int)$_POST['contract_id'] : null;
                $supplier_id  = !empty($supplier_id) ? (int)$supplier_id : null;
                $customer_id  = !empty($customer_id) ? (int)$customer_id : null;
                
                $stmt = $conn->prepare("INSERT INTO payments (payment_date,supplier_id,customer_id,payer_type,payment_type,amount,note,receipt_number,contract_id) VALUES (?,?,?,?,?,?,?,?,?)");
                if (!$stmt) {
                    $msg = 'error:Database error: ' . $conn->error;
                } else {
                    $stmt->bind_param("siissdssi", $date,$supplier_id,$customer_id,$payer_type,$payment_type,$amount,$note,$receipt_number,$contract_id);
                    if (!$stmt->execute()) {
                        $msg = 'error:Failed to record payment: ' . $stmt->error;
                    } else {
                        $msg = 'success:Payment recorded.';
                    }
                    $stmt->close();
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM payments WHERE id=$id");
        $msg = 'success:Payment deleted.';
    }
}

$filter_type = $_GET['type'] ?? '';
$filter_payer = $_GET['payer_type'] ?? '';
$where = "WHERE 1=1";
if ($filter_type)  $where .= " AND p.payment_type='" . $conn->real_escape_string($filter_type) . "'";
if ($filter_payer) $where .= " AND p.payer_type='"   . $conn->real_escape_string($filter_payer) . "'";

$payments = $conn->query("
  SELECT p.*, c.name as customer_name, s.name as supplier_name
  FROM payments p
  LEFT JOIN customers c ON p.customer_id = c.id
  LEFT JOIN suppliers s ON p.supplier_id = s.id
  $where
  ORDER BY p.payment_date DESC, p.id DESC
");
$totals = $conn->query("SELECT SUM(amount) as total FROM payments p $where")->fetch_assoc();

$suppliers = $conn->query("SELECT id, name FROM suppliers ORDER BY name");
$customers = $conn->query("SELECT id, name FROM customers ORDER BY name");
$contracts = $conn->query("SELECT contracts.id, contracts.contract_date, s.name as supplier_name, c.name as customer_name, contracts.description, contracts.qty, contracts.qty_unit, contracts.rate FROM contracts LEFT JOIN customers c ON contracts.customer_id = c.id LEFT JOIN suppliers s ON contracts.supplier_id = s.id ORDER BY contracts.contract_date DESC");


require_once '../includes/header.php';
[$mtype, $mtext] = $msg ? explode(':', $msg, 2) : ['',''];
?>

<?php if ($mtext): ?>
<div class="alert alert-<?= $mtype ?>"><?= htmlspecialchars($mtext) ?></div>
<?php endif; ?>

<div class="page-header">
  <h1>Payments</h1>
  <button class="btn btn-primary" onclick="openModal('addModal')">+ Record Payment</button>
</div>

<form method="GET" class="filter-row">
  <select name="payer_type" class="form-control" onchange="this.form.submit()">
    <option value="">All Parties</option>
    <option value="customer" <?= $filter_payer==='customer'?'selected':'' ?>>Customers</option>
    <option value="supplier" <?= $filter_payer==='supplier'?'selected':'' ?>>Suppliers</option>
    <option value="both" <?= $filter_payer==='both'?'selected':'' ?>>Both</option>
  </select>
  <select name="type" class="form-control" onchange="this.form.submit()">
    <option value="">All Types</option>
    <option value="payment" <?= $filter_type==='payment'?'selected':'' ?>>Payment</option>
    <option value="return" <?= $filter_type==='return'?'selected':'' ?>>Return</option>
  </select>
  <a href="payments.php" class="btn btn-secondary">Clear</a>
</form>

<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead><tr>
        <th>S#</th><th>Date</th><th>Receipt #</th><th>Party</th><th>Party Type</th><th>Amount</th><th>Type</th><th>Note</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php $i=1; while ($row = $payments->fetch_assoc()): ?>
        <tr>
          <td><?= $i++ ?></td>
          <td><?= date('d/m/Y', strtotime($row['payment_date'])) ?></td>
          <td><?= htmlspecialchars($row['receipt_number'] ?? '—') ?></td>
          <td class="td-bold">
            <?php 
              if ($row['payer_type'] === 'both') {
                  echo htmlspecialchars(($row['customer_name'] ?? '—') . ' & ' . ($row['supplier_name'] ?? '—'));
              } else {
                  echo htmlspecialchars($row['payer_type'] === 'customer' ? ($row['customer_name'] ?? '—') : ($row['supplier_name'] ?? '—'));
              }
            ?>
          </td>
          <td><span class="badge badge-<?= $row['payer_type'] === 'customer' ? 'send' : ($row['payer_type'] === 'supplier' ? 'pct' : 'primary') ?>"><?= ucfirst($row['payer_type']) ?></span></td>
          <td class="td-num"><?= number_format($row['amount'], 2) ?></td>
          <td><span class="badge badge-<?= $row['payment_type'] === 'payment' ? 'payment' : 'return' ?>"><?= $row['payment_type'] ?></span></td>
          <td><?= strip_tags($row['note'] ?? '—') ?: '—' ?></td>
          <td>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete payment?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $row['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm">Del</button>
            </form>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
      <tfoot>
        <tr class="totals-row">
          <td colspan="7"><strong>TOTAL</strong></td>
          <td class="td-num"><strong><?= number_format($totals['total'] ?? 0, 2) ?></strong></td>
          <td colspan="3"></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Record Payment</div>
      <button class="modal-close" onclick="closeModal('addModal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-group" style="margin-bottom: 15px; border: 2px dashed #007bff; padding: 20px; text-align: center; border-radius: 8px; background: #f8f9fa;">
          <label for="receipt_upload" style="cursor:pointer; font-weight:bold; color:#007bff; display:block; margin:0;">
            <i class="fas fa-upload" style="margin-right:8px; font-size:1.2em;"></i> Upload Receipt to Auto-fill
          </label>
          <input type="file" id="receipt_upload" accept="image/*" style="display:none;" onchange="processReceipt(this)">
          <div id="receipt_status" style="margin-top: 10px; font-size: 0.9em; color: #666;"></div>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Date *</label>
            <input type="date" name="payment_date" class="form-control" required value="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Payment Type *</label>
            <select name="payment_type" class="form-control">
              <option value="payment">Payment (Received)</option>
              <option value="return">Return Payment</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Receipt Number</label>
            <input type="text" name="receipt_number" class="form-control" placeholder="e.g., RCP-001, Invoice #123">
          </div>
          <div class="form-group">
            <label class="form-label">Customer</label>
            <select name="customer_id" class="form-control">
              <option value="">— Select Customer —</option>
              <?php $customers->data_seek(0); while ($c = $customers->fetch_assoc()): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Supplier</label>
            <select name="supplier_id" class="form-control">
              <option value="">— Select Supplier —</option>
              <?php $suppliers->data_seek(0); while ($s = $suppliers->fetch_assoc()): ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label class="form-label">Link Contract</label>
            <select name="contract_id" class="form-control">
              <option value="">— Select Contract (Optional) —</option>
              <?php $contracts->data_seek(0); while ($ct = $contracts->fetch_assoc()): ?>
                <?php 
                  $label = date('d/m/Y', strtotime($ct['contract_date'])) . ' | ' .
                           ($ct['supplier_name'] ?: '—') . ' | ' .
                           ($ct['customer_name'] ?: '—') . ' | ' .
                           (strip_tags($ct['description'] ?? '') ?: '—') . ' | ' .
                           (float)$ct['qty'] . ' ' .
                           ($ct['qty_unit'] ?: 'METER') . ' | Rs ' .
                           (float)$ct['rate'];
                ?>
                <option value="<?= $ct['id'] ?>">Contract #<?= $ct['id'] ?> - <?= htmlspecialchars($label) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Amount (PKR) *</label>
            <input type="number" name="amount" class="form-control" required>
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label class="form-label">Note</label>
            <textarea name="note" class="form-control rich-editor" placeholder="Optional note…"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Payment</button>
      </div>
    </form>
  </div>
</div>

<script>
async function processReceipt(input) {
  if (!input.files || input.files.length === 0) return;
  const file = input.files[0];
  const statusDiv = document.getElementById('receipt_status');
  
  statusDiv.innerHTML = '<span style="color:#f39c12"><i class="fas fa-spinner fa-spin"></i> Processing receipt, please wait...</span>';
  
  try {
    const formData = new FormData();
    formData.append('receipt', file);

    const endpoint = new URL('ocr_upload.php', window.location.href).href;
    const response = await fetch(endpoint, { method: 'POST', body: formData });

    let result = null;
    try {
      result = await response.json();
    } catch (parseError) {
      throw new Error(`OCR upload failed with status ${response.status}. Server response was not valid JSON.`);
    }

    if (!response.ok || !result.success) {
      throw new Error(result?.error || `HTTP ${response.status}`);
    }

    const text = result.text || '';
    console.log("OCR Text:", text);

    // ── Normalize Text ──────────────────────────────────────────────
    const rawText = text.replace(/[^\x00-\x7F\r\n]+/g, ' ');
    const normalizedText = rawText
      .replace(/\r\n/g, '\n')
      .replace(/[\t ]+/g, ' ')
      .replace(/-{2,}/g, '-')
      .replace(/={2,}/g, '=')
      .replace(/[\*]+/g, '*')
      .replace(/\s{2,}/g, ' ')
      .trim();

    const lines = normalizedText
      .split(/\r?\n/)
      .map(line => line.trim().replace(/[\|\u2014\u2013]+/g, ' '))
      .filter(line => line.length > 0 && /[A-Za-z0-9]/.test(line));

    function normalizeLine(line) {
      return line
        .replace(/[\u2018\u2019\u201C\u201D]/g, '"')
        .replace(/[:;]+/g, ':')
        .replace(/\s*-\s*/g, ' - ')
        .replace(/\s*\|\s*/g, ' | ')
        .replace(/\s{2,}/g, ' ')
        .trim();
    }

    const cleanLines = lines.map(normalizeLine).filter(line => line.length > 0);
    const textForDate = cleanLines.join(' ');

    // ── 1. Extract Amount (Smart) ────────────────────────────────────
    let maxAmount = 0;
    let amountLine = null;

    const priorityHints = [
      ['grand total', 'net total', 'net payable', 'net amount', 'total amount', 'total due', 'balance due', 'amount due', 'payable amount'],
      ['total', 'subtotal', 'sub total'],
      ['deposit', 'received', 'paid', 'cash'],
      ['amount', 'rs', 'pkr', 'rupees']
    ];

    function isLikelyPhoneOrAccount(line) {
      return /\b\d{10,}\b/.test(line.replace(/[\s\-]/g, ''));
    }

   function extractNumbersFromLine(line) {
  // Remove S.No patterns like "1." or "1)" at start
  let cleaned = line.replace(/^\d{1,3}[\.\)]\s*/, '');
  // Remove commas from numbers
  cleaned = cleaned.replace(/,/g, '');
  let matches = cleaned.match(/\b\d+(\.\d{1,2})?\b/g);
  if (!matches) return [];
  return matches
    .map(m => parseFloat(m))
    .filter(n => !isNaN(n) && n >= 10 && n <= 9999999)
    // Skip numbers that look like dates (1-31) or qty (1-9) UNLESS no other number exists
    .filter(n => n >= 100 || matches.length === 1);
}

    let found = false;
for (let hintGroup of priorityHints) {
  if (found) break;
  for (let line of cleanLines) {
    if (isLikelyPhoneOrAccount(line)) continue;
    const lower = line.toLowerCase();
    if (hintGroup.some(hint => lower.includes(hint))) {
      const nums = extractNumbersFromLine(line);
      if (nums.length > 0) {
        // Last number on the line = amount (not S.No or qty)
        const candidate = nums[nums.length - 1];
        if (candidate > maxAmount && candidate >= 10) { // min 10 rupees
          maxAmount = candidate;
          amountLine = line;
          found = true;
        }
      }
    }
  }
}

    // Fallback: scan all lines
    if (maxAmount === 0) {
  for (let line of cleanLines) {
    if (isLikelyPhoneOrAccount(line)) continue;
    if (/\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)\b/i.test(line)) continue;
    if (/^\d{1,3}[\.\)]\s/.test(line)) continue; // Skip S.No lines
    const nums = extractNumbersFromLine(line);
    if (nums.length === 0) continue;
    const candidate = nums[nums.length - 1]; // Last number = amount
    if (candidate > maxAmount) {
      maxAmount = candidate;
      amountLine = line;
    }
  }
}

    if (maxAmount > 0) {
      document.querySelector('input[name="amount"]').value = maxAmount.toFixed(2);
    }

    // ── 2. Extract Date ──────────────────────────────────────────────
    const dateRegexes = [
      { regex: /(?:date|dated|dt\.?|on)\s*[:\-]?\s*(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})/i, format: 'DMY' },
      { regex: /(?:date|dated|dt\.?|on)\s*[:\-]?\s*(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})/i, format: 'YMD' },
      { regex: /(?:date|dated|dt\.?|on)\s*[:\-]?\s*(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+(\d{1,2})(?:st|nd|rd|th)?,?\s+(\d{4})/i, format: 'MDY_STR' },
      { regex: /(?:date|dated|dt\.?|on)\s*[:\-]?\s*(\d{1,2})(?:st|nd|rd|th)?\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s*,?\s*(\d{4})/i, format: 'DMY_STR' },
      { regex: /\b(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})\b/, format: 'YMD' },
      { regex: /\b(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})\b/, format: 'DMY' },
      { regex: /\b(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+(\d{1,2})(?:st|nd|rd|th)?,?\s+(\d{4})\b/i, format: 'MDY_STR' },
      { regex: /\b(\d{1,2})(?:st|nd|rd|th)?\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s*,?\s*(\d{4})\b/i, format: 'DMY_STR' }
    ];

    let finalDate = null;
    let dateTextCandidate = cleanLines.find(line => /(?:date|dated|dt\.?|on)\b/i.test(line) && /\d{1,2}[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})/.test(line));
    if (!dateTextCandidate) dateTextCandidate = textForDate;

    for (let rule of dateRegexes) {
      let m = dateTextCandidate.match(rule.regex);
      if (m) {
        let tempDate = null;
        if (rule.format === 'YMD') {
          tempDate = new Date(parseInt(m[1]), parseInt(m[2]) - 1, parseInt(m[3]));
        } else if (rule.format === 'DMY') {
          let p1 = parseInt(m[1]), p2 = parseInt(m[2]), y = parseInt(m[3]);
          if (y < 100) y += 2000;
          tempDate = p1 > 12 ? new Date(y, p2-1, p1) : new Date(y, p2-1, p1);
        } else if (rule.format === 'MDY_STR') {
          tempDate = new Date(`${m[1]} ${m[2]}, ${m[3]}`);
        } else if (rule.format === 'DMY_STR') {
          tempDate = new Date(`${m[2]} ${m[1]}, ${m[3]}`);
        }
        if (tempDate && !isNaN(tempDate.getTime())) {
          let y = tempDate.getFullYear();
          if (y >= 2000 && y <= 2100) { finalDate = tempDate; break; }
        }
      }
    }

    if (finalDate) {
      let yyyy = finalDate.getFullYear();
      let mm = String(finalDate.getMonth() + 1).padStart(2, '0');
      let dd = String(finalDate.getDate()).padStart(2, '0');
      document.querySelector('input[name="payment_date"]').value = `${yyyy}-${mm}-${dd}`;
    }

    // ── 3. Extract Party Name ────────────────────────────────────────
    let customerSelect = document.querySelector('select[name="customer_id"]');
    let supplierSelect = document.querySelector('select[name="supplier_id"]');

    function findMatch(selectObj) {
      for (let i = 0; i < selectObj.options.length; i++) {
        let opt = selectObj.options[i];
        if (!opt.value) continue;
        let words = opt.text.toLowerCase().split(/\s+/).filter(w => w.length > 2);
        let matches = words.filter(w => text.toLowerCase().includes(w)).length;
        if (matches > 0 && matches >= words.length / 2) return opt.value;
      }
      return null;
    }

    let cMatch = findMatch(customerSelect);
    if (cMatch) {
      document.getElementById('pt_cust').checked = true;
      toggleParty('customer');
      customerSelect.value = cMatch;
    } else {
      let sMatch = findMatch(supplierSelect);
      if (sMatch) {
        document.getElementById('pt_supp').checked = true;
        toggleParty('supplier');
        supplierSelect.value = sMatch;
      }
    }

    // ── 4. Payment Type ──────────────────────────────────────────────
    let typeSelect = document.querySelector('select[name="payment_type"]');
    typeSelect.value = (text.toLowerCase().includes('refund') || text.toLowerCase().includes('return')) ? 'return' : 'payment';

    // ── 5. Note ──────────────────────────────────────────────────────
    let noteLines = ['Auto-filled from receipt:'];
    if (maxAmount > 0) noteLines.push(`Amount: ${maxAmount.toFixed(2)}` + (amountLine ? `  (${amountLine})` : ''));
    if (finalDate) {
      let yyyy = finalDate.getFullYear();
      let mm = String(finalDate.getMonth()+1).padStart(2,'0');
      let dd = String(finalDate.getDate()).padStart(2,'0');
      noteLines.push(`Date: ${dd}/${mm}/${yyyy}`);
    }
    noteLines.push('', ...cleanLines.slice(0, 12));

    let noteText = noteLines.join('\n');
    let noteElem = document.querySelector('textarea[name="note"]');
    noteElem.value = noteText;
    if (typeof $ !== 'undefined' && $(noteElem).summernote) {
      $(noteElem).summernote('code', noteText.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br/>'));
    }

    statusDiv.innerHTML = '<span style="color:#28a745"><i class="fas fa-check-circle"></i> Receipt processed! Please verify the values.</span>';

  } catch (err) {
    console.error(err);
    statusDiv.innerHTML = `<span style="color:#dc3545"><i class="fas fa-times-circle"></i> ${err?.message || 'Error processing receipt.'}</span>`;
    input.value = '';
  }
}

// Form validation before submission
document.querySelector('#addModal form').addEventListener('submit', function(e) {
  const customerId = document.querySelector('select[name="customer_id"]').value;
  const supplierId = document.querySelector('select[name="supplier_id"]').value;
  
  if (!customerId && !supplierId) {
    e.preventDefault();
    alert('Please select at least one party (Customer or Supplier).');
    return false;
  }
});
</script>

<?php require_once '../includes/footer.php'; ?>
