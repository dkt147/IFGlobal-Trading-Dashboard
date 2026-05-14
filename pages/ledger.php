<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/ocr.php';
requireLogin();

$pageTitle = 'Ledger';
$activePage = 'ledger';

$owner = getOwner();

$customers = $conn->query("SELECT id, name FROM customers ORDER BY name");
$suppliers = $conn->query("SELECT id, name FROM suppliers ORDER BY name");

$filter_customer = (int)($_GET['customer_id'] ?? 0);
$filter_supplier = (int)($_GET['supplier_id'] ?? 0);

$party_name = '';
$rows = [];
$contracts_data = [];

if ($filter_customer && $filter_supplier) {
    // Fetch contracts between selected parties
    $contract_query = "SELECT * FROM contracts WHERE customer_id=$filter_customer AND supplier_id=$filter_supplier ORDER BY contract_date DESC";
    $contracts_result = $conn->query($contract_query);
    while ($c = $contracts_result->fetch_assoc()) {
        $contracts_data[] = $c;
    }
    
    // Build merged ledger: delivery orders (debit) + payments (credit)
    $entries = [];
    $party_names = [];

    $cname = $conn->query("SELECT name FROM customers WHERE id=$filter_customer")->fetch_assoc();
    $party_names[] = $cname['name'] ?? '';

    $sname = $conn->query("SELECT name FROM suppliers WHERE id=$filter_supplier")->fetch_assoc();
    $party_names[] = $sname['name'] ?? '';

    // Collect contract IDs between this customer-supplier pair
    $contract_ids = [];
    foreach ($contracts_data as $cd) {
        $contract_ids[] = (int)$cd['id'];
    }
    $contract_ids_str = !empty($contract_ids) ? implode(',', $contract_ids) : '0';

    // Delivery orders: exact pair match OR linked to their contracts
    $dos = $conn->query("
        SELECT do_date as entry_date, description, qty, rate, debit as debit_amt, 0 as credit_amt, 'DO' as entry_type, type as sub_type, id
        FROM delivery_orders
        WHERE (customer_id=$filter_customer AND supplier_id=$filter_supplier)
           OR contract_id IN ($contract_ids_str)
        ORDER BY do_date, id
    ");
    while ($r = $dos->fetch_assoc()) $entries[] = $r;

    // Payments: exact pair match OR linked to contracts OR single-party match
    $pays = $conn->query("
        SELECT payment_date as entry_date, CONCAT('PAYMENT - ',IFNULL(note,'')) as description, 0 as qty, 0 as rate, 0 as debit_amt, amount as credit_amt, 'PAY' as entry_type, payment_type as sub_type, id
        FROM payments
        WHERE (customer_id=$filter_customer AND supplier_id=$filter_supplier)
           OR contract_id IN ($contract_ids_str)
           OR (customer_id=$filter_customer AND supplier_id IS NULL)
           OR (supplier_id=$filter_supplier AND customer_id IS NULL)
        ORDER BY payment_date, id
    ");
    while ($r = $pays->fetch_assoc()) $entries[] = $r;

    // Set combined party name
    $party_name = implode(' & ', $party_names);
    
    // Sort all by date
    usort($entries, fn($a,$b) => strcmp($a['entry_date'],$b['entry_date']));
    
    // Compute running balance
    $balance = 0;
    $total_debit = 0; $total_credit = 0; $total_qty = 0;
    foreach ($entries as &$e) {
        if ($e['entry_type'] === 'DO') {
            if ($e['sub_type'] === 'return') {
                $balance -= $e['debit_amt'];
                $total_debit -= $e['debit_amt'];
            } else {
                $balance += $e['debit_amt'];
                $total_debit += $e['debit_amt'];
                $total_qty += $e['qty'];
            }
        } else {
            if ($e['sub_type'] === 'return') {
                $balance += $e['credit_amt'];
            } else {
                $balance -= $e['credit_amt'];
                $total_credit += $e['credit_amt'];
            }
        }
        $e['balance'] = $balance;
    }
    unset($e);
    $rows = $entries;
}

require_once '../includes/header.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div class="page-header">
  <h1>Ledger</h1>
  <?php if ($party_name): ?>
    <span style="font-family:'Cormorant Garamond',serif; font-size:1.2rem; color:var(--clay);"><?= htmlspecialchars($party_name) ?></span>
  <?php endif; ?>
</div>

<form method="GET" class="filter-row" style="margin-bottom:1.5rem">
  <div style="font-size:0.65rem; letter-spacing:0.15em; text-transform:uppercase; color:var(--ash); align-self:center;">View Ledger For:</div>
  <select name="customer_id" class="form-control" style="min-width:220px" onchange="this.form.submit()">
    <option value="">— Customer —</option>
    <?php $customers->data_seek(0); while ($c = $customers->fetch_assoc()): ?>
      <option value="<?= $c['id'] ?>" <?= $filter_customer==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
    <?php endwhile; ?>
  </select>
  <div style="font-size:0.65rem; color:var(--ash); align-self:center;">+</div>
  <select name="supplier_id" class="form-control" style="min-width:220px" onchange="this.form.submit()">
    <option value="">— Supplier —</option>
    <?php $suppliers->data_seek(0); while ($s = $suppliers->fetch_assoc()): ?>
      <option value="<?= $s['id'] ?>" <?= $filter_supplier==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
    <?php endwhile; ?>
  </select>
  <a href="ledger.php" class="btn btn-secondary">Clear</a>
</form>

<?php if (($filter_customer || $filter_supplier) && !($filter_customer && $filter_supplier)): ?>
<div class="no-data">
  Select both a customer and a supplier to view their mutual contracts and ledger.
</div>
<?php endif; ?>

<?php if ($filter_customer && $filter_supplier): ?>

<div class="card" id="ledger-preview">
  <div class="card-header" style="display: flex; justify-content: space-between; align-items: flex-start;">
    <div>
      <div class="card-title"><?= htmlspecialchars($party_name) ?> — Account Statement</div>
      <div style="font-size:0.65rem; color:var(--ash); letter-spacing:0.1em;">100% Cotton · METER</div>
    </div>
    <button class="btn btn-pdf" onclick="printLedger()">⬇ Download PDF</button>
  </div>
  <div class="card-body">
    <?php if (!empty($contracts_data)): ?>
      <div class="tbl-wrap" style="margin-bottom:1.5rem;">
        <table>
          <thead><tr>
            <th>S#</th>
            <th>Date</th>
            <th>Supplier</th>
            <th>Customer</th>
            <th>Description</th>
            <th>Qty</th>
            <th>Rate</th>
            <th>Debit</th>
            <th>Commission</th>
          </tr></thead>
          <tbody>
          <?php $sn=1; foreach ($contracts_data as $contract): ?>
            <tr>
              <td><?= $sn++ ?></td>
              <td><?= date('d/m/Y', strtotime($contract['contract_date'])) ?></td>
              <td><?php
                $supp = $conn->query("SELECT name FROM suppliers WHERE id={$contract['supplier_id']}")->fetch_assoc();
                echo htmlspecialchars($supp['name'] ?? '—');
              ?></td>
              <td><?php
                $cust = $conn->query("SELECT name FROM customers WHERE id={$contract['customer_id']}")->fetch_assoc();
                echo htmlspecialchars($cust['name'] ?? '—');
              ?></td>
              <td><?= strip_tags($contract['description'] ?? '') ?></td>
              <td class="td-num"><?= number_format($contract['qty'], 2) ?></td>
              <td class="td-num"><?= number_format($contract['rate'], 2) ?></td>
              <td class="td-num"><?= number_format($contract['debit'], 2) ?></td>
              <td class="td-num">
                <?php
                  $comm = 0;
                  if ($contract['commission_type'] === 'percentage') {
                      $comm = $contract['debit'] * ($contract['commission_value'] / 100);
                      echo number_format($comm, 2) . ' (' . $contract['commission_value'] . '%)';
                  } else {
                      $comm = $contract['qty'] * $contract['commission_value'];
                      echo number_format($comm, 2) . ' (' . $contract['commission_value'] . '/unit)';
                  }
                ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <div class="tbl-wrap">
      <table>
        <thead><tr>
          <th>S#</th>
          <th>Date</th>
          <th>Description</th>
          <th>Qty</th>
          <th>Rate</th>
          <th>Debit</th>
          <th>Credit</th>
          <th>Balance</th>
        </tr></thead>
        <tbody>
        <?php $sn=1; foreach ($rows as $row): ?>
        <tr>
          <td><?= $row['entry_type']==='DO' ? $sn++ : '' ?></td>
          <td><?= date('d/m/Y', strtotime($row['entry_date'])) ?></td>
          <td>
            <?php if ($row['entry_type'] === 'DO'): ?>
              <?= strip_tags($row['description'] ?? '') ?>
              <?php if ($row['sub_type']==='return'): ?>
                <span class="badge badge-return" style="margin-left:4px">Return</span>
              <?php endif; ?>
            <?php else: ?>
              <span style="color:var(--success); font-size:0.72rem; letter-spacing:0.1em; text-transform:uppercase;">
                <?= $row['sub_type']==='return' ? 'PAYMENT RETURN' : 'PAYMENT' ?>
              </span>
            <?php endif; ?>
          </td>
          <td class="td-num"><?= $row['qty'] ? number_format($row['qty'],2) : '' ?></td>
          <td class="td-num"><?= $row['rate'] ? number_format($row['rate'],2) : '' ?></td>
          <td class="td-num <?= $row['debit_amt']>0 ? 'ledger-debit' : '' ?>">
            <?= $row['debit_amt'] ? number_format($row['debit_amt'],2) : '' ?>
          </td>
          <td class="td-num <?= $row['credit_amt']>0 ? 'ledger-credit' : '' ?>">
            <?= $row['credit_amt'] ? number_format($row['credit_amt'],2) : '' ?>
          </td>
          <td class="td-num ledger-bal"><?= number_format($row['balance'],2) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr class="totals-row">
          <td colspan="3"><strong>TOTAL</strong></td>
          <td class="td-num"><strong><?= number_format($total_qty,2) ?></strong></td>
          <td></td>
          <td class="td-num"><strong><?= number_format($total_debit,2) ?></strong></td>
          <td class="td-num"><strong><?= number_format($total_credit,2) ?></strong></td>
          <td class="td-num" style="color:<?= $balance>=0?'var(--danger)':'var(--success)' ?>">
            <strong><?= number_format(abs($balance),2) ?> <?= $balance>=0 ? 'DR' : 'CR' ?></strong>
          </td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<?php elseif (!($filter_customer || $filter_supplier)): ?>
<div class="no-data">
  ↑ Select both a customer and a supplier above to view their mutual ledger
</div>
<?php endif; ?>

<?php if ($filter_customer && $filter_supplier): ?>
<div id="ledger-pdf-content" style="display:none;">
<div style="background:white; padding:2.5rem; font-family:'DM Mono',monospace; max-width:800px;">

  <!-- Header -->
  <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:2rem;">
    <div>
      <div style="font-family:'Cormorant Garamond',serif; font-size:1.5rem; font-weight:600; color:#2C2A26; margin-bottom:0.3rem;">
        <?= htmlspecialchars($owner['company_name']) ?>
      </div>
      <div style="font-size:0.72rem; color:#6B6560; letter-spacing:0.08em;"><?= htmlspecialchars($owner['city']) ?></div>
    </div>
    <div style="text-align:right">
      <div style="font-size:0.6rem; letter-spacing:0.25em; text-transform:uppercase; color:#9C7A4A; margin-bottom:0.5rem;">Account Statement</div>
      <div style="font-size:0.72rem; color:#6B6560;">DATE: <strong style="color:#2C2A26"><?= date('d-m-Y') ?></strong></div>
      <div style="font-size:0.72rem; color:#6B6560;">PARTY: <strong style="color:#2C2A26"><?= htmlspecialchars($party_name) ?></strong></div>
    </div>
  </div>

  <!-- Divider -->
  <div style="height:2px; background:linear-gradient(90deg, #C5A882, transparent); margin-bottom:1.5rem;"></div>

  <?php if (!empty($contracts_data)): ?>
  <div style="margin-bottom:1.5rem;">
    <div style="font-family:'Cormorant Garamond',serif; font-size:1rem; font-weight:600; color:#2C2A26; margin-bottom:0.75rem;">Contracts</div>
    <table style="width:100%; border-collapse:collapse; font-size:0.72rem; margin-bottom:1rem;">
      <thead>
        <tr style="background:#EDE8DF; border-bottom:1px solid rgba(197,168,130,0.4);">
          <th style="padding:0.55rem 0.7rem; text-align:left; font-size:0.58rem; font-weight:400; letter-spacing:0.2em; text-transform:uppercase; color:#6B6560;">Date</th>
          <th style="padding:0.55rem 0.7rem; text-align:left; font-size:0.58rem; font-weight:400; letter-spacing:0.2em; text-transform:uppercase; color:#6B6560;">Description</th>
          <th style="padding:0.55rem 0.7rem; text-align:right; font-size:0.58rem; font-weight:400; letter-spacing:0.2em; text-transform:uppercase; color:#6B6560;">Qty</th>
          <th style="padding:0.55rem 0.7rem; text-align:right; font-size:0.58rem; font-weight:400; letter-spacing:0.2em; text-transform:uppercase; color:#6B6560;">Rate</th>
          <th style="padding:0.55rem 0.7rem; text-align:right; font-size:0.58rem; font-weight:400; letter-spacing:0.2em; text-transform:uppercase; color:#6B6560;">Debit</th>
          <th style="padding:0.55rem 0.7rem; text-align:right; font-size:0.58rem; font-weight:400; letter-spacing:0.2em; text-transform:uppercase; color:#6B6560;">Commission</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($contracts_data as $contract): ?>
        <tr style="border-bottom:1px solid rgba(197,168,130,0.15);">
          <td style="padding:0.55rem 0.7rem;"><?= date('d/m/Y', strtotime($contract['contract_date'])) ?></td>
          <td style="padding:0.55rem 0.7rem;"><?= strip_tags($contract['description'] ?? '') ?></td>
          <td style="padding:0.55rem 0.7rem; text-align:right;"><?= number_format($contract['qty'],2) ?></td>
          <td style="padding:0.55rem 0.7rem; text-align:right;"><?= number_format($contract['rate'],2) ?></td>
          <td style="padding:0.55rem 0.7rem; text-align:right;"><?= number_format($contract['debit'],2) ?></td>
          <td style="padding:0.55rem 0.7rem; text-align:right;">
            <?php
              if ($contract['commission_type'] === 'percentage') {
                  echo number_format($contract['debit'] * ($contract['commission_value'] / 100), 2) . ' (' . $contract['commission_value'] . '%)';
              } else {
                  echo number_format($contract['qty'] * $contract['commission_value'], 2) . ' (' . $contract['commission_value'] . '/unit)';
              }
            ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- Table -->
  <table style="width:100%; border-collapse:collapse; font-size:0.72rem; margin-bottom:1.5rem;">
    <thead>
      <tr style="background:#EDE8DF; border-bottom:1px solid rgba(197,168,130,0.4);">
        <th style="padding:0.6rem 0.8rem; text-align:left; font-size:0.58rem; font-weight:400; letter-spacing:0.2em; text-transform:uppercase; color:#6B6560;">Date</th>
        <th style="padding:0.6rem 0.8rem; text-align:left; font-size:0.58rem; font-weight:400; letter-spacing:0.2em; text-transform:uppercase; color:#6B6560;">Description</th>
        <th style="padding:0.6rem 0.8rem; text-align:right; font-size:0.58rem; font-weight:400; letter-spacing:0.2em; text-transform:uppercase; color:#6B6560;">Qty</th>
        <th style="padding:0.6rem 0.8rem; text-align:right; font-size:0.58rem; font-weight:400; letter-spacing:0.2em; text-transform:uppercase; color:#6B6560;">Debit</th>
        <th style="padding:0.6rem 0.8rem; text-align:right; font-size:0.58rem; font-weight:400; letter-spacing:0.2em; text-transform:uppercase; color:#6B6560;">Credit</th>
        <th style="padding:0.6rem 0.8rem; text-align:right; font-size:0.58rem; font-weight:400; letter-spacing:0.2em; text-transform:uppercase; color:#6B6560;">Balance</th>
      </tr>
    </thead>
    <tbody>
    <?php $sn=1; foreach ($rows as $row): ?>
      <tr style="border-bottom:1px solid rgba(197,168,130,0.15);">
        <td style="padding:0.6rem 0.8rem;"><?= date('d/m/Y', strtotime($row['entry_date'])) ?></td>
        <td style="padding:0.6rem 0.8rem;">
          <?php if ($row['entry_type'] === 'DO'): ?>
            <?= strip_tags($row['description'] ?? '') ?>
            <?php if ($row['sub_type']==='return'): ?>
              <span style="color:#9C7A4A; font-size:0.6rem;"> (Return)</span>
            <?php endif; ?>
          <?php else: ?>
            <span style="color:#4CAF50; font-size:0.65rem; letter-spacing:0.1em; text-transform:uppercase;">
              <?= $row['sub_type']==='return' ? 'PAYMENT RETURN' : 'PAYMENT' ?>
            </span>
          <?php endif; ?>
        </td>
        <td style="padding:0.6rem 0.8rem; text-align:right;"><?= $row['qty'] ? number_format($row['qty'],2) : '' ?></td>
        <td style="padding:0.6rem 0.8rem; text-align:right; color:#F44336;"><?= $row['debit_amt'] ? number_format($row['debit_amt'],2) : '' ?></td>
        <td style="padding:0.6rem 0.8rem; text-align:right; color:#4CAF50;"><?= $row['credit_amt'] ? number_format($row['credit_amt'],2) : '' ?></td>
        <td style="padding:0.6rem 0.8rem; text-align:right; font-weight:500;"><?= number_format($row['balance'],2) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Totals -->
  <div style="display:flex; justify-content:flex-end; margin-bottom:2rem;">
    <div style="min-width:300px;">
      <div style="display:flex; justify-content:space-between; padding:0.4rem 0; font-size:0.72rem; border-bottom:1px solid rgba(197,168,130,0.2);">
        <span style="color:#6B6560;">Total Debit</span>
        <span>PKR <?= number_format($total_debit,2) ?></span>
      </div>
      <div style="display:flex; justify-content:space-between; padding:0.4rem 0; font-size:0.72rem; border-bottom:1px solid rgba(197,168,130,0.2);">
        <span style="color:#6B6560;">Total Credit</span>
        <span>PKR <?= number_format($total_credit,2) ?></span>
      </div>
      <div style="display:flex; justify-content:space-between; padding:0.6rem 0; font-size:1rem; font-family:'Cormorant Garamond',serif; font-weight:600; border-top:2px solid #C5A882;">
        <span>Balance</span>
        <span style="color:<?= $balance>=0?'#F44336':'#4CAF50' ?>">PKR <?= number_format(abs($balance),2) ?> <?= $balance>=0 ? 'DR' : 'CR' ?></span>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <div style="font-size:0.65rem; color:#6B6560; border-top:1px solid rgba(197,168,130,0.3); padding-top:1rem; letter-spacing:0.08em;">
    Account Statement for <?= htmlspecialchars($party_name) ?> as of <?= date('d-m-Y') ?>
  </div>

</div>
</div>
<?php endif; ?>

<script>
function printLedger() {
  const element = document.getElementById('ledger-pdf-content');
  element.style.display = 'block'; // Temporarily show for PDF generation
  const opt = {
    margin: 0.5,
    filename: 'ledger-statement-<?= htmlspecialchars($party_name) ?>-<?= date('Y-m-d') ?>.pdf',
    image: { type: 'jpeg', quality: 0.98 },
    html2canvas: { scale: 2, useCORS: true },
    jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
  };
  html2pdf().set(opt).from(element).save().then(() => {
    element.style.display = 'none'; // Hide again after generation
  });
}
</script>

<?php require_once '../includes/footer.php'; ?>
