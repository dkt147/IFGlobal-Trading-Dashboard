<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();

$pageTitle = 'Commission PDF';
$activePage = 'commission';

$customers = $conn->query("SELECT id, name FROM customers ORDER BY name");
$suppliers = $conn->query("SELECT id, name FROM suppliers ORDER BY name");

$filter_supplier = (int)($_GET['supplier_id'] ?? 0);
$filter_customer = (int)($_GET['customer_id'] ?? 0);

$party_name = '';
$party_type = '';
$invoice_data = [];

if ($filter_supplier || $filter_customer) {
    $owner = getOwner();
    
    // Get next invoice number
    $inv_row = $conn->query("SELECT last_invoice FROM invoice_counter LIMIT 1")->fetch_assoc();
    $invoice_num = ($inv_row['last_invoice'] ?? 14) + 1;
    
    if ($filter_supplier) {
        $party_type = 'supplier';
        $p = $conn->query("SELECT * FROM suppliers WHERE id=$filter_supplier")->fetch_assoc();
        $party_name = $p['name'] ?? '';
        
        $contracts = $conn->query("
            SELECT c.*, cu.name as customer_name
            FROM contracts c
            LEFT JOIN customers cu ON c.customer_id = cu.id
            WHERE c.supplier_id = $filter_supplier
            ORDER BY c.contract_date
        ");
    } else {
        $party_type = 'customer';
        $p = $conn->query("SELECT * FROM customers WHERE id=$filter_customer")->fetch_assoc();
        $party_name = $p['name'] ?? '';
        
        $contracts = $conn->query("
            SELECT c.*, s.name as supplier_name
            FROM contracts c
            LEFT JOIN suppliers s ON c.supplier_id = s.id
            WHERE c.customer_id = $filter_customer
            ORDER BY c.contract_date
        ");
    }
    
    $invoice_data = [];
    $subtotal = 0;
    while ($c = $contracts->fetch_assoc()) {
        $commission = 0;
        if ($c['commission_type'] === 'percentage') {
            $commission = $c['debit'] * ($c['commission_value'] / 100);
            $comm_label = $c['commission_value'] . '%';
        } else {
            $commission = $c['qty'] * $c['commission_value'];
            $comm_label = 'PKR ' . $c['commission_value'] . '/unit';
        }
        
        $other_party = $party_type === 'supplier' ? ($c['customer_name'] ?? '—') : ($c['supplier_name'] ?? '—');
        
        $invoice_data[] = [
            'date'         => $c['contract_date'],
            'description'  => ($other_party . ' — ' . $c['description']),
            'qty'          => $c['qty'],
            'qty_unit'     => $c['qty_unit'],
            'rate'         => $c['rate'],
            'debit'        => $c['debit'],
            'comm_label'   => $comm_label,
            'commission'   => $commission,
        ];
        $subtotal += $commission;
    }
}

require_once '../includes/header.php';
?>

<div class="page-header">
  <h1>Commission PDF Generator</h1>
</div>

<div style="display:grid; grid-template-columns:300px 1fr; gap:1.5rem; align-items:start">

<!-- Left: Selector -->
<div class="card">
  <div class="card-header"><div class="card-title">Select Party</div></div>
  <div class="card-body">
    <form method="GET">
      <div class="form-group" style="margin-bottom:1rem">
        <label class="form-label">Supplier</label>
        <select name="supplier_id" class="form-control" onchange="this.form.customer_id.value=''; this.form.submit()">
          <option value="">— Select Supplier —</option>
          <?php $suppliers->data_seek(0); while ($s = $suppliers->fetch_assoc()): ?>
            <option value="<?= $s['id'] ?>" <?= $filter_supplier==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div style="text-align:center; font-size:0.65rem; color:var(--ash); margin:0.5rem 0; letter-spacing:0.15em">— OR —</div>
      <div class="form-group" style="margin-bottom:1rem">
        <label class="form-label">Customer</label>
        <select name="customer_id" class="form-control" onchange="this.form.supplier_id.value=''; this.form.submit()">
          <option value="">— Select Customer —</option>
          <?php $customers->data_seek(0); while ($c = $customers->fetch_assoc()): ?>
            <option value="<?= $c['id'] ?>" <?= $filter_customer==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <a href="commission_pdf.php" class="btn btn-secondary" style="width:100%; justify-content:center">Clear</a>
    </form>

    <?php if ($invoice_data): ?>
    <div style="margin-top:1.5rem; border-top:1px solid var(--border); padding-top:1rem;">
      <button class="btn btn-pdf" style="width:100%; justify-content:center" onclick="printInvoice()">
        ⬇ Download PDF
      </button>
      <div style="font-size:0.6rem; color:var(--ash); text-align:center; margin-top:0.5rem; letter-spacing:0.1em">
        Use browser Print → Save as PDF
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Right: Invoice Preview -->
<div>
<?php if ($invoice_data): ?>
<div id="invoice-preview">
<div style="background:white; padding:2.5rem; border:1px solid var(--border); font-family:'DM Mono',monospace; max-width:800px;">

  <!-- Invoice Header -->
  <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:2rem;">
    <div>
      <div style="font-family:'Cormorant Garamond',serif; font-size:1.5rem; font-weight:600; color:#2C2A26; margin-bottom:0.3rem;">
        <?= htmlspecialchars($owner['company_name']) ?>
      </div>
      <div style="font-size:0.72rem; color:#6B6560; letter-spacing:0.08em;"><?= htmlspecialchars($owner['city']) ?></div>
    </div>
    <div style="text-align:right">
      <div style="font-size:0.6rem; letter-spacing:0.25em; text-transform:uppercase; color:#9C7A4A; margin-bottom:0.5rem;">Commission Bill</div>
      <div style="font-size:0.72rem; color:#6B6560;">DATE: <strong style="color:#2C2A26"><?= date('d-m-Y') ?></strong></div>
      <div style="font-size:0.72rem; color:#6B6560;">INVOICE #: <strong style="color:#2C2A26"><?= $invoice_num ?></strong></div>
      <div style="font-size:0.72rem; color:#6B6560;">DUE DATE: <strong style="color:#2C2A26"><?= date('d-m-Y', strtotime('+7 days')) ?></strong></div>
    </div>
  </div>

  <!-- Divider -->
  <div style="height:2px; background:linear-gradient(90deg, #C5A882, transparent); margin-bottom:1.5rem;"></div>

  <!-- Bill To -->
  <div style="margin-bottom:1.5rem;">
    <div style="font-size:0.58rem; letter-spacing:0.25em; text-transform:uppercase; color:#9C7A4A; margin-bottom:0.4rem;">Bill To</div>
    <div style="font-size:1rem; font-weight:500; color:#2C2A26;"><?= htmlspecialchars($party_name) ?></div>
    <div style="font-size:0.7rem; color:#6B6560; margin-top:0.2rem; text-transform:uppercase; letter-spacing:0.1em;">
      <?= $party_type === 'supplier' ? 'Supplier' : 'Customer' ?>
    </div>
  </div>

  <!-- Line Items Table -->
  <table style="width:100%; border-collapse:collapse; font-size:0.72rem; margin-bottom:1.5rem;">
    <thead>
      <tr style="background:#EDE8DF; border-bottom:1px solid rgba(197,168,130,0.4);">
        <th style="padding:0.6rem 0.8rem; text-align:left; font-size:0.58rem; font-weight:400; letter-spacing:0.2em; text-transform:uppercase; color:#6B6560;">Description</th>
        <th style="padding:0.6rem 0.8rem; text-align:right; font-size:0.58rem; font-weight:400; letter-spacing:0.2em; text-transform:uppercase; color:#6B6560;">Qty</th>
        <th style="padding:0.6rem 0.8rem; text-align:right; font-size:0.58rem; font-weight:400; letter-spacing:0.2em; text-transform:uppercase; color:#6B6560;">Rate</th>
        <th style="padding:0.6rem 0.8rem; text-align:right; font-size:0.58rem; font-weight:400; letter-spacing:0.2em; text-transform:uppercase; color:#6B6560;">Debit</th>
        <th style="padding:0.6rem 0.8rem; text-align:right; font-size:0.58rem; font-weight:400; letter-spacing:0.2em; text-transform:uppercase; color:#6B6560;">Comm</th>
        <th style="padding:0.6rem 0.8rem; text-align:right; font-size:0.58rem; font-weight:400; letter-spacing:0.2em; text-transform:uppercase; color:#6B6560;">Amount</th>
      </tr>
    </thead>
    <tbody>
    <?php $grand_comm = 0; foreach ($invoice_data as $item): $grand_comm += $item['commission']; ?>
      <tr style="border-bottom:1px solid rgba(197,168,130,0.15);">
        <td style="padding:0.6rem 0.8rem;">
          <?= htmlspecialchars($item['description']) ?>
          <div style="font-size:0.6rem; color:#9C7A4A; margin-top:2px;"><?= date('d/m/Y', strtotime($item['date'])) ?></div>
        </td>
        <td style="padding:0.6rem 0.8rem; text-align:right;"><?= number_format($item['qty'],2) ?> <?= $item['qty_unit'] ?></td>
        <td style="padding:0.6rem 0.8rem; text-align:right;"><?= number_format($item['rate'],2) ?></td>
        <td style="padding:0.6rem 0.8rem; text-align:right;"><?= number_format($item['debit'],2) ?></td>
        <td style="padding:0.6rem 0.8rem; text-align:right; color:#9C7A4A; font-size:0.65rem;"><?= $item['comm_label'] ?></td>
        <td style="padding:0.6rem 0.8rem; text-align:right; font-weight:500;"><?= number_format($item['commission'],2) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Totals -->
  <div style="display:flex; justify-content:flex-end; margin-bottom:2rem;">
    <div style="min-width:260px;">
      <div style="display:flex; justify-content:space-between; padding:0.4rem 0; font-size:0.72rem; border-bottom:1px solid rgba(197,168,130,0.2);">
        <span style="color:#6B6560;">Subtotal</span>
        <span>PKR <?= number_format($grand_comm,2) ?></span>
      </div>
      <div style="display:flex; justify-content:space-between; padding:0.4rem 0; font-size:0.72rem; border-bottom:1px solid rgba(197,168,130,0.2);">
        <span style="color:#6B6560;">Tax</span>
        <span>PKR 0.00</span>
      </div>
      <div style="display:flex; justify-content:space-between; padding:0.6rem 0; font-size:1rem; font-family:'Cormorant Garamond',serif; font-weight:600; border-top:2px solid #C5A882;">
        <span>TOTAL</span>
        <span style="color:#9C7A4A;">PKR <?= number_format($grand_comm,2) ?></span>
      </div>
    </div>
  </div>

  <!-- Payment Info -->
  <div style="background:#F8F5EF; border-left:3px solid #C5A882; padding:1rem 1.2rem; margin-bottom:1.5rem;">
    <div style="font-size:0.58rem; letter-spacing:0.2em; text-transform:uppercase; color:#9C7A4A; margin-bottom:0.5rem;">Make all payments payable to</div>
    <div style="font-size:0.75rem; white-space:pre-line; color:#2C2A26; line-height:1.6;"><?= htmlspecialchars($owner['bank_details'] ?? '') ?></div>
  </div>

  <!-- Footer -->
  <div style="font-size:0.65rem; color:#6B6560; border-top:1px solid rgba(197,168,130,0.3); padding-top:1rem; letter-spacing:0.08em;">
    If you have any questions about this invoice, please contact<br>
    <strong style="color:#2C2A26"><?= htmlspecialchars($owner['full_name']) ?>, <?= htmlspecialchars($owner['phone'] ?? '') ?>, <?= htmlspecialchars($owner['email'] ?? '') ?></strong>
  </div>

</div>
</div><!-- #invoice-preview -->

<?php elseif (!($filter_supplier || $filter_customer)): ?>
<div class="card" style="display:flex; align-items:center; justify-content:center; min-height:300px;">
  <div class="no-data">← Select a supplier or customer to preview their commission bill</div>
</div>
<?php else: ?>
<div class="card" style="display:flex; align-items:center; justify-content:center; min-height:200px;">
  <div class="no-data">No contracts found for this party.</div>
</div>
<?php endif; ?>
</div><!-- right col -->
</div><!-- grid -->

<script>
function printInvoice() {
  const content = document.getElementById('invoice-preview').innerHTML;
  const win = window.open('', '_blank');
  win.document.write(`<!DOCTYPE html><html><head>
    <title>Commission Invoice</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500;600&family=DM+Mono:wght@300;400&display=swap" rel="stylesheet">
    <style>
      body { margin:0; padding:2rem; font-family:'DM Mono',monospace; background:white; }
      @media print { body { padding:0; } }
    </style>
  </head><body>${content}</body></html>`);
  win.document.close();
  setTimeout(() => win.print(), 800);
}
</script>

<?php require_once '../includes/footer.php'; ?>
