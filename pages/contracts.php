<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();

$pageTitle = 'Contracts';
$activePage = 'contracts';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $date        = $_POST['contract_date'];
        $supplier_id = $_POST['supplier_id'] ?: null;
        $customer_id = $_POST['customer_id'] ?: null;
        $note        = strip_tags(trim($_POST['note']));
        $desc        = strip_tags(trim($_POST['description']));
        $qty         = (float)$_POST['qty'];
        $qty_unit    = $_POST['qty_unit'];
        $rate        = (float)$_POST['rate'];
        $comm_type   = $_POST['commission_type'];
        $comm_val    = (float)$_POST['commission_value'];
        $stmt = $conn->prepare("INSERT INTO contracts (contract_date,supplier_id,customer_id,note,description,qty,qty_unit,rate,commission_type,commission_value) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("siissdsdsd", $date,$supplier_id,$customer_id,$note,$desc,$qty,$qty_unit,$rate,$comm_type,$comm_val);
        $stmt->execute();
        $msg = 'success:Contract saved.';
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM contracts WHERE id=$id");
        $msg = 'success:Contract deleted.';
    }
}

// Pagination setup
$records_per_page = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $records_per_page;

// Total records count
$total_result = $conn->query("SELECT COUNT(*) as cnt FROM contracts");
$total_records = $total_result->fetch_assoc()['cnt'];
$total_pages = (int)ceil($total_records / $records_per_page);
$page = min($page, max(1, $total_pages)); // clamp page

$contracts = $conn->query("
  SELECT c.*, s.name as supplier_name, cu.name as customer_name
  FROM contracts c
  LEFT JOIN suppliers s ON c.supplier_id = s.id
  LEFT JOIN customers cu ON c.customer_id = cu.id
  ORDER BY c.contract_date DESC
  LIMIT $records_per_page OFFSET $offset
");
$suppliers = $conn->query("SELECT id, name FROM suppliers ORDER BY name");
$customers = $conn->query("SELECT id, name FROM customers ORDER BY name");

require_once '../includes/header.php';
[$mtype, $mtext] = $msg ? explode(':', $msg, 2) : ['',''];
?>

<?php if ($mtext): ?>
<div class="alert alert-<?= $mtype ?>"><?= htmlspecialchars($mtext) ?></div>
<?php endif; ?>

<style>
.pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.2rem;
    border-top: 1px solid var(--border);
    flex-wrap: wrap;
    gap: 0.5rem;
}

.pagination-info {
    font-size: 0.82rem;
    color: var(--text-muted);
}

.pagination-links {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.pg-ellipsis {
    padding: 0 4px;
    color: var(--text-muted);
    font-size: 0.85rem;
}
</style>

<div class="page-header">
    <h1>Contracts</h1>
    <button class="btn btn-primary" onclick="openModal('addModal')">+ New Contract</button>
</div>

<div class="card">
    <div class="tbl-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Supplier</th>
                    <th>Customer</th>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Unit</th>
                    <th>Rate</th>
                    <th>Debit</th>
                    <th>Commission</th>
                    <th>Note</th>
                    <th>Act</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $contracts->fetch_assoc()): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($row['contract_date'])) ?></td>
                    <td><?= htmlspecialchars($row['supplier_name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($row['customer_name'] ?? '—') ?></td>
                    <td><?= strip_tags($row['description'] ?? '—') ?></td>
                    <td class="td-num"><?= number_format($row['qty'], 0) ?></td>
                    <td><?= htmlspecialchars($row['qty_unit']) ?></td>
                    <td class="td-num"><?= number_format($row['rate'], 2) ?></td>
                    <td class="td-num"><?= number_format($row['debit'], 0) ?></td>
                    <td>
                        <?php if ($row['commission_type'] === 'percentage'): ?>
                        <span class="badge badge-pct"><?= $row['commission_value'] ?>%</span>
                        <?php else: ?>
                        <span class="badge badge-pct">PKR <?= number_format($row['commission_value'], 2) ?>/unit</span>
                        <?php endif; ?>
                    </td>
                    <td><?= strip_tags($row['note'] ?? '—') ?: '—' ?></td>
                    <td>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete contract?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Del</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <span class="pagination-info">
            Showing <?= $offset + 1 ?>–<?= min($offset + $records_per_page, $total_records) ?> of <?= $total_records ?>
            records
        </span>
        <div class="pagination-links">
            <?php if ($page > 1): ?>
            <a href="?page=1" class="btn btn-sm btn-secondary">«</a>
            <a href="?page=<?= $page - 1 ?>" class="btn btn-sm btn-secondary">‹ Prev</a>
            <?php endif; ?>

            <?php
        $range = 2;
        $start = max(1, $page - $range);
        $end   = min($total_pages, $page + $range);
        if ($start > 1): ?><span class="pg-ellipsis">…</span><?php endif;
        for ($i = $start; $i <= $end; $i++): ?>
            <a href="?page=<?= $i ?>"
                class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?>"><?= $i ?></a>
            <?php endfor;
        if ($end < $total_pages): ?><span class="pg-ellipsis">…</span><?php endif;
      ?>

            <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?>" class="btn btn-sm btn-secondary">Next ›</a>
            <a href="?page=<?= $total_pages ?>" class="btn btn-sm btn-secondary">»</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal" style="max-width:700px">
        <div class="modal-header">
            <div class="modal-title">New Contract</div>
            <button class="modal-close" onclick="closeModal('addModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Date *</label>
                        <input type="date" name="contract_date" class="form-control" required
                            value="<?= date('Y-m-d') ?>">
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
                        <label class="form-label">Description *</label>
                        <textarea name="description" class="form-control rich-editor"
                            placeholder="e.g. 60x40/90x70 46"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quantity *</label>
                        <input type="number" name="qty" id="qty" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Qty Unit</label>
                        <select name="qty_unit" class="form-control">
                            <option value="METER">METER</option>
                            <option value="YARD">YARN</option>
                            <option value="KG">KG</option>
                            <option value="PCS">PCS</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Rate (PKR) *</label>
                        <input type="number" name="rate" id="rate" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Debit (auto)</label>
                        <div id="debit_preview"
                            style="padding:0.6rem 0.8rem; background:var(--cream); border:1px solid var(--border); font-size:0.8rem; color:var(--bronze);">
                            PKR 0.00</div>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label class="form-label">Commission Type</label>
                        <div class="comm-toggle">
                            <input type="radio" name="commission_type" id="pct" value="percentage" checked>
                            <label for="pct">Percentage (%)</label>
                            <input type="radio" name="commission_type" id="unit" value="unit_based">
                            <label for="unit">Per Unit (PKR)</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" id="comm_value_label">Commission %</label>
                        <input type="number" name="commission_value" class="form-control" value="1.0" required>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label class="form-label">Note</label>
                        <textarea name="note" class="form-control rich-editor" placeholder="Optional note…"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Contract</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>