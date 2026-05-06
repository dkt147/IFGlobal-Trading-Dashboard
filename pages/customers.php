<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();

$pageTitle = 'Customers';
$activePage = 'customers';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['name']);
        $desc = trim($_POST['description']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $stmt = $conn->prepare("INSERT INTO customers (name, description, phone, address) VALUES (?,?,?,?)");
        $stmt->bind_param("ssss", $name, $desc, $phone, $address);
        $stmt->execute();
        $msg = 'success:Customer added successfully.';
    } elseif ($action === 'edit') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name']);
        $desc = trim($_POST['description']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $stmt = $conn->prepare("UPDATE customers SET name=?, description=?, phone=?, address=? WHERE id=?");
        $stmt->bind_param("ssssi", $name, $desc, $phone, $address, $id);
        $stmt->execute();
        $msg = 'success:Customer updated.';
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM customers WHERE id=$id");
        $msg = 'success:Customer deleted.';
    }
}

$customers = $conn->query("SELECT c.*, COUNT(DISTINCT d.id) as do_cnt FROM customers c LEFT JOIN delivery_orders d ON d.customer_id = c.id GROUP BY c.id ORDER BY c.name");
require_once '../includes/header.php';
[$mtype, $mtext] = $msg ? explode(':', $msg, 2) : ['',''];
?>

<?php if ($mtext): ?>
<div class="alert alert-<?= $mtype ?>"><?= htmlspecialchars($mtext) ?></div>
<?php endif; ?>

<div class="page-header">
  <h1>Customers</h1>
  <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Customer</button>
</div>

<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead><tr>
        <th>#</th><th>Name</th><th>Description</th><th>Phone</th><th>Address</th><th>Orders</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php $i=1; while ($row = $customers->fetch_assoc()): ?>
        <tr>
          <td><?= $i++ ?></td>
          <td class="td-bold"><?= htmlspecialchars($row['name']) ?></td>
          <td><?= htmlspecialchars($row['description'] ?? '—') ?></td>
          <td><?= htmlspecialchars($row['phone'] ?? '—') ?></td>
          <td><?= htmlspecialchars($row['address'] ?? '—') ?></td>
          <td class="td-num"><?= $row['do_cnt'] ?></td>
          <td>
            <button class="btn btn-secondary btn-sm" onclick='editCustomer(<?= json_encode($row) ?>)'>Edit</button>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this customer?')">
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
      <div class="modal-title">Add Customer</div>
      <button class="modal-close" onclick="closeModal('addModal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group" style="grid-column:1/-1">
            <label class="form-label">Customer Name *</label>
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
        <button type="submit" class="btn btn-primary">Save Customer</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Edit Customer</div>
      <button class="modal-close" onclick="closeModal('editModal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit_id">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group" style="grid-column:1/-1">
            <label class="form-label">Customer Name *</label>
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
        <button type="submit" class="btn btn-primary">Update Customer</button>
      </div>
    </form>
  </div>
</div>

<script>
function editCustomer(data) {
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
