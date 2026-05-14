<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();

$pageTitle = 'Suppliers';
$activePage = 'suppliers';
$msg = '';

// Handle add/edit/delete
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

$suppliers = $conn->query("SELECT s.*, COUNT(DISTINCT c.id) as contract_cnt FROM suppliers s LEFT JOIN contracts c ON c.supplier_id = s.id GROUP BY s.id ORDER BY s.name");
require_once '../includes/header.php';
[$mtype, $mtext] = $msg ? explode(':', $msg, 2) : ['',''];
?>

<?php if ($mtext): ?>
<div class="alert alert-<?= $mtype ?>"><?= htmlspecialchars($mtext) ?></div>
<?php endif; ?>

<div class="page-header">
  <h1>Suppliers</h1>
  <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Supplier</button>
</div>

<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead><tr>
        <th>#</th><th>Name</th><th>Description</th><th>Phone</th><th>Address</th><th>Contracts</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php $i=1; while ($row = $suppliers->fetch_assoc()): ?>
        <tr>
          <td><?= $i++ ?></td>
          <td class="td-bold"><?= htmlspecialchars($row['name']) ?></td>
          <td><?= strip_tags($row['description'] ?? '—') ?: '—' ?></td>
          <td><?= htmlspecialchars($row['phone'] ?? '—') ?></td>
          <td><?= htmlspecialchars($row['address'] ?? '—') ?></td>
          <td class="td-num"><?= $row['contract_cnt'] ?></td>
          <td>
            <button class="btn btn-secondary btn-sm" onclick='editSupplier(<?= json_encode($row) ?>)'>Edit</button>
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
