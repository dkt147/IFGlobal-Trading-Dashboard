<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();

$pageTitle = 'Suppliers';
$activePage = 'suppliers';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name']);
        $desc = strip_tags(trim($_POST['description']));
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $stmt = $conn->prepare("INSERT INTO suppliers (name, description, phone, address) VALUES (?,?,?,?)");
        $stmt->bind_param("ssss", $name, $desc, $phone, $address);
        $stmt->execute();
        $msg = 'success:Supplier added successfully.';
    } elseif ($action === 'edit') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name']);
        $desc = strip_tags(trim($_POST['description']));
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $stmt = $conn->prepare("UPDATE suppliers SET name=?, description=?, phone=?, address=? WHERE id=?");
        $stmt->bind_param("ssssi", $name, $desc, $phone, $address, $id);
        $stmt->execute();
        $msg = 'success:Supplier updated.';
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM suppliers WHERE id=$id");
        $msg = 'success:Supplier deleted.';
    }
}

// Pagination setup
$records_per_page = 15;
$page = max(1, (int)($_GET['page'] ?? 1));

// Total count
$total_result  = $conn->query("SELECT COUNT(*) as cnt FROM suppliers");
$total_records = $total_result->fetch_assoc()['cnt'];
$total_pages   = (int)ceil($total_records / $records_per_page);
$page          = min($page, max(1, $total_pages));
$offset        = ($page - 1) * $records_per_page;

$suppliers = $conn->query("
    SELECT s.*, COUNT(DISTINCT c.id) as contract_cnt
    FROM suppliers s
    LEFT JOIN contracts c ON c.supplier_id = s.id
    GROUP BY s.id
    ORDER BY s.name
    LIMIT $records_per_page OFFSET $offset
");

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
    <h1>Suppliers</h1>
    <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Supplier</button>
</div>

<div class="card">
    <div class="tbl-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th>Contracts</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = $offset + 1; while ($row = $suppliers->fetch_assoc()): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td class="td-bold"><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= strip_tags($row['description'] ?? '—') ?: '—' ?></td>
                    <td><?= htmlspecialchars($row['phone'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($row['address'] ?? '—') ?></td>
                    <td class="td-num"><?= $row['contract_cnt'] ?></td>
                    <td>
                        <button class="btn btn-secondary btn-sm"
                            onclick='editSupplier(<?= json_encode($row) ?>)'>Edit</button>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this supplier?')">
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
            suppliers
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
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Add Supplier</div>
            <button class="modal-close" onclick="closeModal('addModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group" style="grid-column:1/-1">
                        <label class="form-label">Supplier Name *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control rich-editor"></textarea>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Supplier</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Edit Supplier</div>
            <button class="modal-close" onclick="closeModal('editModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group" style="grid-column:1/-1">
                        <label class="form-label">Supplier Name *</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control">
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_description" class="form-control rich-editor"></textarea>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label class="form-label">Address</label>
                        <textarea name="address" id="edit_address" class="form-control"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Supplier</button>
            </div>
        </form>
    </div>
</div>

<script>
function editSupplier(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_name').value = data.name;
    document.getElementById('edit_phone').value = data.phone || '';
    if ($('#edit_description').length) {
        $('#edit_description').summernote('code', data.description || '');
    } else {
        document.getElementById('edit_description').value = data.description || '';
    }
    document.getElementById('edit_address').value = data.address || '';
    openModal('editModal');
}
</script>

<?php require_once '../includes/footer.php'; ?>