<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();

$pageTitle = 'Delivery Orders';
$activePage = 'delivery';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $date        = $_POST['do_date'];
        $contract_id = $_POST['contract_id'] ?: null;
        $supplier_id = $_POST['supplier_id'] ?: null;
        $customer_id = $_POST['customer_id'] ?: null;
        $desc        = strip_tags(trim($_POST['description']));
        $qty         = (float)$_POST['qty'];
        $rate        = (float)$_POST['rate'];
        $type        = $_POST['type'];
        $stmt = $conn->prepare("INSERT INTO delivery_orders (do_date,contract_id,supplier_id,customer_id,description,qty,rate,type) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param("siiiisds", $date,$contract_id,$supplier_id,$customer_id,$desc,$qty,$rate,$type);
        $stmt->execute();
        $msg = 'success:Delivery order saved.';
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM delivery_orders WHERE id=$id");
        $msg = 'success:Order deleted.';
    }
}

// Filters
$filter_customer = (int)($_GET['customer_id'] ?? 0);
$filter_supplier = (int)($_GET['supplier_id'] ?? 0);
$filter_type = $_GET['type'] ?? '';
$where = "WHERE 1=1";
if ($filter_customer) $where .= " AND d.customer_id=$filter_customer";
if ($filter_supplier) $where .= " AND d.supplier_id=$filter_supplier";
if ($filter_type) $where .= " AND d.type='" . $conn->real_escape_string($filter_type) . "'";

$orders = $conn->query("
  SELECT d.*, c.name as customer_name, s.name as supplier_name, ct.description as contract_desc
  FROM delivery_orders d
  LEFT JOIN customers c ON d.customer_id = c.id
  LEFT JOIN suppliers s ON d.supplier_id = s.id
  LEFT JOIN contracts ct ON d.contract_id = ct.id
  $where
  ORDER BY d.do_date DESC, d.id DESC
");

$totals = $conn->query("SELECT SUM(qty) as tqty, SUM(debit) as tdebit FROM delivery_orders d $where")->fetch_assoc();

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
  <h1>Delivery Orders</h1>
  <button class="btn btn-primary" onclick="openModal('addModal')">+ New Delivery Order</button>
</div>

<!-- Filters -->
<form method="GET" class="filter-row">
  <select name="customer_id" class="form-control" onchange="this.form.submit()">
    <option value="">All Customers</option>
    <?php $customers->data_seek(0); while ($c = $customers->fetch_assoc()): ?>
      <option value="<?= $c['id'] ?>" <?= $filter_customer==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
    <?php endwhile; ?>
  </select>
  <select name="supplier_id" class="form-control" onchange="this.form.submit()">
    <option value="">All Suppliers</option>
    <?php $suppliers->data_seek(0); while ($s = $suppliers->fetch_assoc()): ?>
      <option value="<?= $s['id'] ?>" <?= $filter_supplier==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
    <?php endwhile; ?>
  </select>
  <select name="type" class="form-control" onchange="this.form.submit()">
    <option value="">All Types</option>
    <option value="send" <?= $filter_type==='send'?'selected':'' ?>>Send</option>
    <option value="return" <?= $filter_type==='return'?'selected':'' ?>>Return</option>
  </select>
  <a href="delivery_orders.php" class="btn btn-secondary">Clear</a>
</form>

<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead><tr>
        <th>S#</th><th>Date</th><th>Supplier</th><th>Customer</th><th>Description</th>
        <th>Qty</th><th>Rate</th><th>Debit</th><th>Type</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php $i=1; while ($row = $orders->fetch_assoc()): ?>
        <tr>
          <td><?= $i++ ?></td>
          <td><?= date('d/m/Y', strtotime($row['do_date'])) ?></td>
          <td><?= htmlspecialchars($row['supplier_name'] ?? '—') ?></td>
          <td><?= htmlspecialchars($row['customer_name'] ?? '—') ?></td>
          <td><?= strip_tags($row['description'] ?? $row['contract_desc'] ?? '—') ?></td>
          <td class="td-num"><?= number_format($row['qty'], 2) ?></td>
          <td class="td-num"><?= number_format($row['rate'], 2) ?></td>
          <td class="td-num"><?= number_format($row['debit'], 2) ?></td>
          <td><span class="badge badge-<?= $row['type'] ?>"><?= $row['type'] ?></span></td>
          <td>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')">
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
          <td colspan="5"><strong>TOTALS</strong></td>
          <td class="td-num"><strong><?= number_format($totals['tqty'] ?? 0, 2) ?></strong></td>
          <td></td>
          <td class="td-num"><strong><?= number_format($totals['tdebit'] ?? 0, 2) ?></strong></td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
  <div class="modal" style="max-width:680px">
    <div class="modal-header">
      <div class="modal-title">New Delivery Order</div>
      <button class="modal-close" onclick="closeModal('addModal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Date *</label>
            <input type="date" name="do_date" class="form-control" required value="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Type *</label>
            <select name="type" class="form-control">
              <option value="send">Send</option>
              <option value="return">Return</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Supplier</label>
            <select name="supplier_id" class="form-control">
              <option value="">— None —</option>
              <?php $suppliers->data_seek(0); while ($s = $suppliers->fetch_assoc()): ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Customer</label>
            <select name="customer_id" class="form-control">
              <option value="">— None —</option>
              <?php $customers->data_seek(0); while ($c = $customers->fetch_assoc()): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label class="form-label">Link Contract (optional)</label>
            <select name="contract_id" class="form-control" id="contract_select" onchange="fillFromContract(this)">
              <option value="">— Select Contract —</option>
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
                <option value="<?= $ct['id'] ?>" data-qty="<?= $ct['qty'] ?>" data-rate="<?= $ct['rate'] ?>" data-desc="<?= htmlspecialchars(strip_tags($ct['description'] ?? '')) ?>">
                  Contract #<?= $ct['id'] ?> - <?= htmlspecialchars($label) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label class="form-label">Description *</label>
            <textarea name="description" id="do_desc" class="form-control rich-editor" placeholder="e.g. 60x40/90x70 46"></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Quantity *</label>
            <input type="number" name="qty" id="qty" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Rate (PKR) *</label>
            <input type="number" name="rate" id="rate" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Debit (auto)</label>
            <div id="debit_preview" style="padding:0.6rem 0.8rem; background:var(--cream); border:1px solid var(--border); font-size:0.8rem; color:var(--bronze);">PKR 0.00</div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Order</button>
      </div>
    </form>
  </div>
</div>

<script>
function fillFromContract(sel) {
  const opt = sel.options[sel.selectedIndex];
  if (!opt.value) return;
  if ($('#do_desc').length) {
    $('#do_desc').summernote('code', opt.dataset.desc || '');
  } else {
    document.getElementById('do_desc').value = opt.dataset.desc || '';
  }
  document.getElementById('rate').value = opt.dataset.rate;
  // trigger debit calc
  document.getElementById('rate').dispatchEvent(new Event('input'));
}
</script>

<?php require_once '../includes/footer.php'; ?>
