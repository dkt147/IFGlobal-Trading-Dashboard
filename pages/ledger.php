<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();

$pageTitle = 'Ledger';
$activePage = 'ledger';

$customers = $conn->query("SELECT id, name FROM customers ORDER BY name");
$suppliers = $conn->query("SELECT id, name FROM suppliers ORDER BY name");

$filter_customer = (int)($_GET['customer_id'] ?? 0);
$filter_supplier = (int)($_GET['supplier_id'] ?? 0);

$party_name = '';
$rows = [];

if ($filter_customer || $filter_supplier) {
    // Build merged ledger: delivery orders (debit) + payments (credit)
    $entries = [];
    
    if ($filter_customer) {
        $cname = $conn->query("SELECT name FROM customers WHERE id=$filter_customer")->fetch_assoc();
        $party_name = $cname['name'] ?? '';
        
        // Delivery orders = DEBIT (we supplied fabric, they owe us)
        $dos = $conn->query("
            SELECT do_date as entry_date, description, qty, rate, debit as debit_amt, 0 as credit_amt, 'DO' as entry_type, type as sub_type, id
            FROM delivery_orders
            WHERE customer_id=$filter_customer
            ORDER BY do_date, id
        ");
        while ($r = $dos->fetch_assoc()) $entries[] = $r;
        
        // Payments = CREDIT (they paid us)
        $pays = $conn->query("
            SELECT payment_date as entry_date, CONCAT('PAYMENT - ',IFNULL(note,'')) as description, 0 as qty, 0 as rate, 0 as debit_amt, amount as credit_amt, 'PAY' as entry_type, payment_type as sub_type, id
            FROM payments
            WHERE customer_id=$filter_customer
            ORDER BY payment_date, id
        ");
        while ($r = $pays->fetch_assoc()) $entries[] = $r;
        
    } elseif ($filter_supplier) {
        $sname = $conn->query("SELECT name FROM suppliers WHERE id=$filter_supplier")->fetch_assoc();
        $party_name = $sname['name'] ?? '';
        
        $dos = $conn->query("
            SELECT do_date as entry_date, description, qty, rate, debit as debit_amt, 0 as credit_amt, 'DO' as entry_type, type as sub_type, id
            FROM delivery_orders
            WHERE supplier_id=$filter_supplier
            ORDER BY do_date, id
        ");
        while ($r = $dos->fetch_assoc()) $entries[] = $r;
        
        $pays = $conn->query("
            SELECT payment_date as entry_date, CONCAT('PAYMENT - ',IFNULL(note,'')) as description, 0 as qty, 0 as rate, 0 as debit_amt, amount as credit_amt, 'PAY' as entry_type, payment_type as sub_type, id
            FROM payments
            WHERE supplier_id=$filter_supplier
            ORDER BY payment_date, id
        ");
        while ($r = $pays->fetch_assoc()) $entries[] = $r;
    }
    
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

<div class="page-header">
  <h1>Ledger</h1>
  <?php if ($party_name): ?>
    <span style="font-family:'Cormorant Garamond',serif; font-size:1.2rem; color:var(--clay);"><?= htmlspecialchars($party_name) ?></span>
  <?php endif; ?>
</div>

<form method="GET" class="filter-row" style="margin-bottom:1.5rem">
  <div style="font-size:0.65rem; letter-spacing:0.15em; text-transform:uppercase; color:var(--ash); align-self:center;">View Ledger For:</div>
  <select name="customer_id" class="form-control" style="min-width:220px" onchange="this.form.supplier_id.value=''; this.form.submit()">
    <option value="">— Customer —</option>
    <?php $customers->data_seek(0); while ($c = $customers->fetch_assoc()): ?>
      <option value="<?= $c['id'] ?>" <?= $filter_customer==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
    <?php endwhile; ?>
  </select>
  <div style="font-size:0.65rem; color:var(--ash); align-self:center;">OR</div>
  <select name="supplier_id" class="form-control" style="min-width:220px" onchange="this.form.customer_id.value=''; this.form.submit()">
    <option value="">— Supplier —</option>
    <?php $suppliers->data_seek(0); while ($s = $suppliers->fetch_assoc()): ?>
      <option value="<?= $s['id'] ?>" <?= $filter_supplier==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
    <?php endwhile; ?>
  </select>
  <a href="ledger.php" class="btn btn-secondary">Clear</a>
</form>

<?php if ($filter_customer || $filter_supplier): ?>

<div class="card">
  <div class="card-header">
    <div class="card-title"><?= htmlspecialchars($party_name) ?> — Account Statement</div>
    <div style="font-size:0.65rem; color:var(--ash); letter-spacing:0.1em;">100% Cotton · METER</div>
  </div>
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
              <?= htmlspecialchars($row['description'] ?? '') ?>
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
  ↑ Select a customer or supplier above to view their ledger
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
