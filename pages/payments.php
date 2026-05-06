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
        $payer_type   = $_POST['payer_type'];
        $supplier_id  = $_POST['supplier_id'] ?: null;
        $customer_id  = $_POST['customer_id'] ?: null;
        $payment_type = $_POST['payment_type'];
        $amount       = (float)$_POST['amount'];
        $note         = trim($_POST['note']);
        $stmt = $conn->prepare("INSERT INTO payments (payment_date,supplier_id,customer_id,payer_type,payment_type,amount,note) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("siissds", $date,$supplier_id,$customer_id,$payer_type,$payment_type,$amount,$note);
        $stmt->execute();
        $msg = 'success:Payment recorded.';
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
        <th>S#</th><th>Date</th><th>Party</th><th>Party Type</th><th>Amount</th><th>Type</th><th>Note</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php $i=1; while ($row = $payments->fetch_assoc()): ?>
        <tr>
          <td><?= $i++ ?></td>
          <td><?= date('d/m/Y', strtotime($row['payment_date'])) ?></td>
          <td class="td-bold">
            <?= htmlspecialchars($row['payer_type'] === 'customer' ? ($row['customer_name'] ?? '—') : ($row['supplier_name'] ?? '—')) ?>
          </td>
          <td><span class="badge badge-<?= $row['payer_type'] === 'customer' ? 'send' : 'pct' ?>"><?= $row['payer_type'] ?></span></td>
          <td class="td-num"><?= number_format($row['amount'], 2) ?></td>
          <td><span class="badge badge-<?= $row['payment_type'] === 'payment' ? 'payment' : 'return' ?>"><?= $row['payment_type'] ?></span></td>
          <td><?= htmlspecialchars($row['note'] ?? '—') ?></td>
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
          <td colspan="4"><strong>TOTAL</strong></td>
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
          <div class="form-group" style="grid-column:1/-1">
            <label class="form-label">Party Type *</label>
            <div class="comm-toggle">
              <input type="radio" name="payer_type" id="pt_cust" value="customer" checked onchange="toggleParty('customer')">
              <label for="pt_cust">Customer</label>
              <input type="radio" name="payer_type" id="pt_supp" value="supplier" onchange="toggleParty('supplier')">
              <label for="pt_supp">Supplier</label>
            </div>
          </div>
          <div class="form-group" id="cust_group" style="grid-column:1/-1">
            <label class="form-label">Customer</label>
            <select name="customer_id" class="form-control">
              <option value="">— Select Customer —</option>
              <?php $customers->data_seek(0); while ($c = $customers->fetch_assoc()): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="form-group" id="supp_group" style="display:none; grid-column:1/-1">
            <label class="form-label">Supplier</label>
            <select name="supplier_id" class="form-control">
              <option value="">— Select Supplier —</option>
              <?php $suppliers->data_seek(0); while ($s = $suppliers->fetch_assoc()): ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Amount (PKR) *</label>
            <input type="number" name="amount" class="form-control" step="0.01" required>
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
function toggleParty(type) {
  document.getElementById('cust_group').style.display = type === 'customer' ? '' : 'none';
  document.getElementById('supp_group').style.display = type === 'supplier' ? '' : 'none';
}
</script>

<?php require_once '../includes/footer.php'; ?>
