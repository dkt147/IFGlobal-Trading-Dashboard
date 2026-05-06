<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();

$pageTitle = 'Dashboard';
$activePage = 'dashboard';

// Stats
$stats = [];
$r = $conn->query("SELECT COUNT(*) as c FROM suppliers");
$stats['suppliers'] = $r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM customers");
$stats['customers'] = $r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM contracts");
$stats['contracts'] = $r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM delivery_orders");
$stats['delivery_orders'] = $r->fetch_assoc()['c'];
$r = $conn->query("SELECT COALESCE(SUM(debit),0) as t FROM delivery_orders WHERE type='send'");
$stats['total_debit'] = $r->fetch_assoc()['t'];
$r = $conn->query("SELECT COALESCE(SUM(amount),0) as t FROM payments WHERE payment_type='payment'");
$stats['total_payments'] = $r->fetch_assoc()['t'];
$r = $conn->query("SELECT COUNT(*) as c FROM payments");
$stats['payment_count'] = $r->fetch_assoc()['c'];

// Recent delivery orders
$recent_do = $conn->query("
  SELECT d.*, c.name as customer_name, s.name as supplier_name
  FROM delivery_orders d
  LEFT JOIN customers c ON d.customer_id = c.id
  LEFT JOIN suppliers s ON d.supplier_id = s.id
  ORDER BY d.created_at DESC LIMIT 8
");

// Recent payments
$recent_pay = $conn->query("
  SELECT p.*, c.name as customer_name, s.name as supplier_name
  FROM payments p
  LEFT JOIN customers c ON p.customer_id = c.id
  LEFT JOIN suppliers s ON p.supplier_id = s.id
  ORDER BY p.created_at DESC LIMIT 8
");

require_once '../includes/header.php';
?>

<div class="stats-row">
  <div class="stat-card">
    <div class="stat-label">Total Suppliers</div>
    <div class="stat-value"><?= $stats['suppliers'] ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Total Customers</div>
    <div class="stat-value"><?= $stats['customers'] ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Contracts</div>
    <div class="stat-value"><?= $stats['contracts'] ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Delivery Orders</div>
    <div class="stat-value"><?= $stats['delivery_orders'] ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Total Debit</div>
    <div class="stat-value" style="font-size:1.3rem">PKR <?= number_format($stats['total_debit'], 0) ?></div>
    <div class="stat-sub">From delivery orders</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Total Received</div>
    <div class="stat-value" style="font-size:1.3rem">PKR <?= number_format($stats['total_payments'], 0) ?></div>
    <div class="stat-sub"><?= $stats['payment_count'] ?> payments</div>
  </div>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">

<div class="card">
  <div class="card-header">
    <div class="card-title">Recent Delivery Orders</div>
    <a href="delivery_orders.php" class="btn btn-secondary btn-sm">View All</a>
  </div>
  <div class="tbl-wrap">
    <table>
      <thead><tr>
        <th>Date</th><th>Customer</th><th>Qty</th><th>Debit</th><th>Type</th>
      </tr></thead>
      <tbody>
      <?php while ($row = $recent_do->fetch_assoc()): ?>
        <tr>
          <td><?= date('d/m/y', strtotime($row['do_date'])) ?></td>
          <td><?= htmlspecialchars($row['customer_name'] ?? '—') ?></td>
          <td class="td-num"><?= number_format($row['qty'], 0) ?></td>
          <td class="td-num"><?= number_format($row['debit'], 0) ?></td>
          <td><span class="badge badge-<?= $row['type'] ?>"><?= $row['type'] ?></span></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">Recent Payments</div>
    <a href="payments.php" class="btn btn-secondary btn-sm">View All</a>
  </div>
  <div class="tbl-wrap">
    <table>
      <thead><tr>
        <th>Date</th><th>Party</th><th>Amount</th><th>Type</th>
      </tr></thead>
      <tbody>
      <?php while ($row = $recent_pay->fetch_assoc()): ?>
        <tr>
          <td><?= date('d/m/y', strtotime($row['payment_date'])) ?></td>
          <td><?= htmlspecialchars($row['payer_type'] === 'customer' ? ($row['customer_name'] ?? '—') : ($row['supplier_name'] ?? '—')) ?></td>
          <td class="td-num"><?= number_format($row['amount'], 0) ?></td>
          <td>
            <span class="badge badge-<?= $row['payment_type'] === 'payment' ? 'payment' : 'return' ?>">
              <?= $row['payment_type'] ?>
            </span>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

</div>

<?php require_once '../includes/footer.php'; ?>
