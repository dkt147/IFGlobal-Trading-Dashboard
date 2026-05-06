<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?? 'IF Global Sourcing' ?></title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300&family=DM+Mono:wght@300;400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/textile_app/css/app.css">
<!-- Summernote Lite Rich Text Editor -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
<script>
  $(document).ready(function() {
      $('.rich-editor').summernote({
        height: 250,
        toolbar: [
          ['style', ['style']],
          ['font', ['bold', 'italic', 'underline', 'clear']],
          ['color', ['color']],
          ['para', ['ul', 'ol', 'paragraph']],
          ['table', ['table']],
          ['insert', ['link', 'picture']],
          ['view', ['codeview', 'help']]
        ]
      });
  });
</script>
<script src="/textile_app/js/app.js"></script>
</head>
<body>

<nav class="sidebar">
  <div class="sidebar-brand">
    <div class="sb-logo">IF</div>
    <div>
      <div class="sb-name">IF Global</div>
      <div class="sb-sub">Sourcing</div>
    </div>
  </div>

  <div class="nav-section-label">Main</div>
  <a href="/textile_app/pages/dashboard.php" class="nav-link <?= ($activePage==='dashboard')?'active':'' ?>">
    <span class="nav-icon">◈</span> Dashboard
  </a>

  <div class="nav-section-label">Transactions</div>
  <a href="/textile_app/pages/delivery_orders.php" class="nav-link <?= ($activePage==='delivery')?'active':'' ?>">
    <span class="nav-icon">◎</span> Delivery Orders
  </a>
  <a href="/textile_app/pages/payments.php" class="nav-link <?= ($activePage==='payments')?'active':'' ?>">
    <span class="nav-icon">◇</span> Payments
  </a>

  <div class="nav-section-label">Master Data</div>
  <a href="/textile_app/pages/contracts.php" class="nav-link <?= ($activePage==='contracts')?'active':'' ?>">
    <span class="nav-icon">▣</span> Contracts
  </a>
  <a href="/textile_app/pages/suppliers.php" class="nav-link <?= ($activePage==='suppliers')?'active':'' ?>">
    <span class="nav-icon">△</span> Suppliers
  </a>
  <a href="/textile_app/pages/customers.php" class="nav-link <?= ($activePage==='customers')?'active':'' ?>">
    <span class="nav-icon">○</span> Customers
  </a>

  <div class="nav-section-label">Reports</div>
  <a href="/textile_app/pages/ledger.php" class="nav-link <?= ($activePage==='ledger')?'active':'' ?>">
    <span class="nav-icon">≡</span> Ledger
  </a>
  <a href="/textile_app/pages/commission_pdf.php" class="nav-link <?= ($activePage==='commission')?'active':'' ?>">
    <span class="nav-icon">◉</span> Commission PDF
  </a>

  <div class="sidebar-footer">
    <div class="owner-name"><?= htmlspecialchars(getOwner()['full_name'] ?? 'Owner') ?></div>
    <a href="/Global-Sourcing/logout.php" class="logout-btn">Sign Out</a>
  </div>
</nav>

<div class="main-wrap">
  <div class="top-bar">
    <div class="page-title-bar"><?= $pageTitle ?? '' ?></div>
    <div class="top-bar-right">
      <span class="date-display"><?= date('d M Y') ?></span>
    </div>
  </div>
  <div class="page-content">
