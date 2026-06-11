<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();

$pageTitle = 'Commission PDF';
$activePage = 'commission';

$customers = $conn->query("SELECT id, name FROM customers ORDER BY name");
$suppliers = $conn->query("SELECT id, name FROM suppliers ORDER BY name");

$filter_supplier = (int)($_GET['supplier_id'] ?? 0);
$filter_customer = (int)($_GET['customer_id'] ?? 0);
$selected_do_id  = (int)($_GET['do_id'] ?? 0);

$party_name    = '';
$party_type    = '';
$invoice_data  = [];
$delivery_list = [];

if ($filter_supplier || $filter_customer) {
    $owner = getOwner();

    $inv_row     = $conn->query("SELECT last_invoice FROM invoice_counter LIMIT 1")->fetch_assoc();
    $invoice_num = ($inv_row['last_invoice'] ?? 14) + 1;

    // Delivery list sirf tab show ho jab DONO select hon
    $do_where = "WHERE 1=1";
    if ($filter_customer && $filter_supplier) {
        $do_where .= " AND d.customer_id = $filter_customer AND d.supplier_id = $filter_supplier";
    } else {
        // Agar sirf ek select hai to delivery list empty rakho
        $do_where .= " AND 1=0";
    }

    if ($filter_customer && $filter_supplier) {
        $party_type = 'both';
        $cname = $conn->query("SELECT name FROM customers WHERE id=$filter_customer")->fetch_assoc();
        $sname = $conn->query("SELECT name FROM suppliers WHERE id=$filter_supplier")->fetch_assoc();
        $party_name = ($cname['name'] ?? '') . ' & ' . ($sname['name'] ?? '');
    } elseif ($filter_supplier) {
        $party_type = 'supplier';
        $p = $conn->query("SELECT name FROM suppliers WHERE id=$filter_supplier")->fetch_assoc();
        $party_name = $p['name'] ?? '';
    } else {
        $party_type = 'customer';
        $p = $conn->query("SELECT name FROM customers WHERE id=$filter_customer")->fetch_assoc();
        $party_name = $p['name'] ?? '';
    }

    // Fetch delivery orders
    $dos = $conn->query("
        SELECT d.*,
               c.name  AS customer_name,
               s.name  AS supplier_name,
               ct.commission_type,
               ct.commission_value,
               ct.qty_unit
        FROM delivery_orders d
        LEFT JOIN customers  c  ON d.customer_id  = c.id
        LEFT JOIN suppliers  s  ON d.supplier_id  = s.id
        LEFT JOIN contracts  ct ON d.contract_id  = ct.id
        $do_where
        ORDER BY d.do_date DESC, d.id DESC
    ");

    while ($row = $dos->fetch_assoc()) {
        // Fallback: agar contract linked nahi to supplier+customer se dhundho
        if (empty($row['commission_type'])) {
            $fb_where = "WHERE 1=1";
            if ($row['supplier_id']) $fb_where .= " AND supplier_id = " . (int)$row['supplier_id'];
            if ($row['customer_id']) $fb_where .= " AND customer_id = " . (int)$row['customer_id'];
            $fb = $conn->query("SELECT commission_type, commission_value, qty_unit FROM contracts $fb_where ORDER BY contract_date DESC LIMIT 1")->fetch_assoc();
            if ($fb) {
                $row['commission_type']  = $fb['commission_type'];
                $row['commission_value'] = $fb['commission_value'];
                if (empty($row['qty_unit'])) $row['qty_unit'] = $fb['qty_unit'];
            }
        }
        $delivery_list[] = $row;
    }

    // Build invoice for selected delivery order
    if ($selected_do_id) {
        foreach ($delivery_list as $d) {
            if ($d['id'] == $selected_do_id) {
                $debit = (float)$d['debit'];
                $qty   = (float)$d['qty'];

                if ($d['commission_type'] === 'percentage') {
                    $commission = $debit * ($d['commission_value'] / 100);
                    $comm_label = $d['commission_value'] . '%';
                } elseif ($d['commission_type'] === 'unit_based') {
                    $commission = $qty * $d['commission_value'];
                    $comm_label = 'PKR ' . $d['commission_value'] . '/unit';
                } else {
                    $commission = 0;
                    $comm_label = 'N/A';
                }

                $desc = strip_tags($d['description'] ?? '—');
                if ($party_type === 'supplier') {
                    $desc = ($d['customer_name'] ?? '—') . ' — ' . $desc;
                } elseif ($party_type === 'customer') {
                    $desc = ($d['supplier_name'] ?? '—') . ' — ' . $desc;
                }

                $invoice_data[] = [
                    'date'        => $d['do_date'],
                    'description' => $desc,
                    'qty'         => $qty,
                    'qty_unit'    => $d['qty_unit'] ?? '',
                    'rate'        => (float)$d['rate'],
                    'debit'       => $debit,
                    'comm_label'  => $comm_label,
                    'commission'  => $commission,
                ];
                break;
            }
        }
    }
}

require_once '../includes/header.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div class="page-header">
    <h1>Commission PDF Generator</h1>
</div>

<div style="display:grid; grid-template-columns:320px 1fr; gap:1.5rem; align-items:start">

    <!-- ───── Left panel ───── -->
    <div>
        <div class="card" style="margin-bottom:1rem">
            <div class="card-header">
                <div class="card-title">Select Party</div>
            </div>
            <div class="card-body">
                <form method="GET">
                    <div class="form-group" style="margin-bottom:1rem">
                        <label class="form-label">Customer</label>
                        <select name="customer_id" class="form-control" onchange="this.form.submit()">
                            <option value="">— Select Customer —</option>
                            <?php $customers->data_seek(0); while ($c = $customers->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>" <?= $filter_customer==$c['id']?'selected':'' ?>>
                                <?= htmlspecialchars($c['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div
                        style="text-align:center; font-size:0.65rem; color:var(--ash); margin:0.5rem 0; letter-spacing:0.15em">
                        — AND / OR —</div>
                    <div class="form-group" style="margin-bottom:1rem">
                        <label class="form-label">Supplier</label>
                        <select name="supplier_id" class="form-control" onchange="this.form.submit()">
                            <option value="">— Select Supplier —</option>
                            <?php $suppliers->data_seek(0); while ($s = $suppliers->fetch_assoc()): ?>
                            <option value="<?= $s['id'] ?>" <?= $filter_supplier==$s['id']?'selected':'' ?>>
                                <?= htmlspecialchars($s['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <?php if ($selected_do_id): ?>
                    <input type="hidden" name="do_id" value="<?= $selected_do_id ?>">
                    <?php endif; ?>
                    <a href="commission_pdf.php" class="btn btn-secondary"
                        style="width:100%; justify-content:center">Clear</a>
                </form>
            </div>
        </div>

        <!-- Delivery Orders List -->
        <?php if ($delivery_list): ?>
        <div class="card">
            <div class="card-header">
                <div class="card-title">Delivery Orders</div>
            </div>
            <div class="card-body" style="padding:0">
                <div style="overflow:auto; max-height:520px;">
                    <?php foreach ($delivery_list as $d): ?>
                    <?php
            $isActive  = ($d['id'] == $selected_do_id);
            $do_debit  = (float)$d['debit'];
            $do_url    = "commission_pdf.php?supplier_id=$filter_supplier&customer_id=$filter_customer&do_id={$d['id']}";

            // Preview commission in list
            if ($d['commission_type'] === 'percentage') {
                $prev_comm = $do_debit * ($d['commission_value'] / 100);
                $prev_label = $d['commission_value'] . '%';
            } elseif ($d['commission_type'] === 'unit_based') {
                $prev_comm = (float)$d['qty'] * $d['commission_value'];
                $prev_label = 'PKR ' . $d['commission_value'] . '/unit';
            } else {
                $prev_comm  = 0;
                $prev_label = 'N/A';
            }
          ?>
                    <a href="<?= $do_url ?>" style="
            display:block;
            padding:0.75rem 1rem;
            border-bottom:1px solid var(--border);
            text-decoration:none;
            background:<?= $isActive ? 'var(--cream)' : 'white' ?>;
            border-left:3px solid <?= $isActive ? 'var(--bronze)' : 'transparent' ?>;
          ">
                        <div
                            style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.25rem;">
                            <span
                                style="font-size:0.68rem; color:var(--ash)"><?= date('d/m/Y', strtotime($d['do_date'])) ?></span>
                            <span class="badge badge-<?= $d['type'] ?>"><?= $d['type'] ?></span>
                        </div>
                        <div
                            style="font-size:0.78rem; font-weight:500; color:var(--dark); margin-bottom:0.2rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <?= htmlspecialchars(strip_tags($d['description'] ?? '—')) ?>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:0.7rem; color:var(--bronze); font-weight:600;">
                                PKR <?= number_format($do_debit, 2) ?>
                                <span
                                    style="font-weight:400; color:var(--ash); margin-left:0.3rem"><?= number_format((float)$d['qty'], 2) ?>
                                    <?= htmlspecialchars($d['qty_unit'] ?? '') ?></span>
                            </span>
                            <?php if ($prev_comm > 0): ?>
                            <span style="font-size:0.68rem; color:#2a7a4a; font-weight:600;">
                                Comm: PKR <?= number_format($prev_comm, 2) ?>
                            </span>
                            <?php elseif ($prev_label === 'N/A'): ?>
                            <span style="font-size:0.68rem; color:var(--ash);">No commission</span>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php elseif ($filter_supplier || $filter_customer): ?>
        <div class="card">
            <div class="card-body" style="text-align:center; padding:2rem; color:var(--ash); font-size:0.8rem;">
                No delivery orders found for this party.
            </div>
        </div>
        <?php endif; ?>
    </div><!-- left -->

    <!-- ───── Right: Invoice Preview ───── -->
    <div>
        <?php if ($invoice_data): ?>

        <div style="display:flex; justify-content:flex-end; margin-bottom:1rem;">
            <button class="btn btn-primary" onclick="printInvoice()">⬇ Download PDF</button>
        </div>

        <div id="invoice-preview">
            <div
                style="background:white; padding:2.5rem; border:1px solid var(--border); font-family:'DM Mono',monospace; max-width:800px;">

                <!-- Header -->
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:2rem;">
                    <div>
                        <div
                            style="font-family:'Cormorant Garamond',serif; font-size:1.5rem; font-weight:600; color:#2C2A26; margin-bottom:0.3rem;">
                            <?= htmlspecialchars($owner['company_name']) ?>
                        </div>
                        <div style="font-size:0.72rem; color:#6B6560; letter-spacing:0.08em;">
                            <?= htmlspecialchars($owner['city']) ?></div>
                    </div>
                    <div style="text-align:right">
                        <div
                            style="font-size:0.6rem; letter-spacing:0.25em; text-transform:uppercase; color:#9C7A4A; margin-bottom:0.5rem;">
                            Commission Bill</div>
                        <div style="font-size:0.72rem; color:#6B6560;">DATE: <strong
                                style="color:#2C2A26"><?= date('d-m-Y') ?></strong></div>
                        <div style="font-size:0.72rem; color:#6B6560;">INVOICE #: <strong
                                style="color:#2C2A26"><?= $invoice_num ?></strong></div>
                        <div style="font-size:0.72rem; color:#6B6560;">DUE DATE: <strong
                                style="color:#2C2A26"><?= date('d-m-Y', strtotime('+7 days')) ?></strong></div>
                    </div>
                </div>

                <div style="height:2px; background:linear-gradient(90deg, #C5A882, transparent); margin-bottom:1.5rem;">
                </div>

                <!-- Bill To -->
                <div style="margin-bottom:1.5rem;">
                    <div
                        style="font-size:0.58rem; letter-spacing:0.25em; text-transform:uppercase; color:#9C7A4A; margin-bottom:0.4rem;">
                        Bill To</div>
                    <div style="font-size:1rem; font-weight:500; color:#2C2A26;"><?= htmlspecialchars($party_name) ?>
                    </div>
                    <div
                        style="font-size:0.7rem; color:#6B6560; margin-top:0.2rem; text-transform:uppercase; letter-spacing:0.1em;">
                        <?php if ($party_type==='both'): ?>Customer & Supplier
                        <?php elseif ($party_type==='supplier'): ?>Supplier
                        <?php else: ?>Customer<?php endif; ?>
                    </div>
                </div>

                <!-- Table -->
                <table style="width:100%; border-collapse:collapse; font-size:0.72rem; margin-bottom:1.5rem;">
                    <thead>
                        <tr style="background:#EDE8DF; border-bottom:1px solid rgba(197,168,130,0.4);">
                            <th
                                style="padding:0.6rem 0.8rem; text-align:left; font-size:0.58rem; font-weight:400; letter-spacing:0.2em; text-transform:uppercase; color:#6B6560;">
                                Description</th>
                            <th
                                style="padding:0.6rem 0.8rem; text-align:right; font-size:0.58rem; font-weight:400; letter-spacing:0.2em; text-transform:uppercase; color:#6B6560;">
                                Qty</th>
                            <th
                                style="padding:0.6rem 0.8rem; text-align:right; font-size:0.58rem; font-weight:400; letter-spacing:0.2em; text-transform:uppercase; color:#6B6560;">
                                Rate</th>
                            <th
                                style="padding:0.6rem 0.8rem; text-align:right; font-size:0.58rem; font-weight:400; letter-spacing:0.2em; text-transform:uppercase; color:#6B6560;">
                                Debit</th>
                            <th
                                style="padding:0.6rem 0.8rem; text-align:right; font-size:0.58rem; font-weight:400; letter-spacing:0.2em; text-transform:uppercase; color:#6B6560;">
                                Comm</th>
                            <th
                                style="padding:0.6rem 0.8rem; text-align:right; font-size:0.58rem; font-weight:400; letter-spacing:0.2em; text-transform:uppercase; color:#6B6560;">
                                Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $grand_comm = 0; foreach ($invoice_data as $item): $grand_comm += $item['commission']; ?>
                        <tr style="border-bottom:1px solid rgba(197,168,130,0.15);">
                            <td style="padding:0.6rem 0.8rem;">
                                <?= htmlspecialchars($item['description']) ?>
                                <div style="font-size:0.6rem; color:#9C7A4A; margin-top:2px;">
                                    <?= date('d/m/Y', strtotime($item['date'])) ?></div>
                            </td>
                            <td style="padding:0.6rem 0.8rem; text-align:right;"><?= number_format($item['qty'],2) ?>
                                <?= htmlspecialchars($item['qty_unit']) ?></td>
                            <td style="padding:0.6rem 0.8rem; text-align:right;"><?= number_format($item['rate'],2) ?>
                            </td>
                            <td style="padding:0.6rem 0.8rem; text-align:right;"><?= number_format($item['debit'],2) ?>
                            </td>
                            <td style="padding:0.6rem 0.8rem; text-align:right; color:#9C7A4A; font-size:0.65rem;">
                                <?= htmlspecialchars($item['comm_label']) ?></td>
                            <td style="padding:0.6rem 0.8rem; text-align:right; font-weight:500;">
                                <?= number_format($item['commission'],2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Totals -->
                <div style="display:flex; justify-content:flex-end; margin-bottom:2rem;">
                    <div style="min-width:260px;">
                        <div
                            style="display:flex; justify-content:space-between; padding:0.4rem 0; font-size:0.72rem; border-bottom:1px solid rgba(197,168,130,0.2);">
                            <span style="color:#6B6560;">Subtotal</span><span>PKR
                                <?= number_format($grand_comm,2) ?></span>
                        </div>
                        <div
                            style="display:flex; justify-content:space-between; padding:0.4rem 0; font-size:0.72rem; border-bottom:1px solid rgba(197,168,130,0.2);">
                            <span style="color:#6B6560;">Tax</span><span>PKR 0.00</span>
                        </div>
                        <div
                            style="display:flex; justify-content:space-between; padding:0.6rem 0; font-size:1rem; font-family:'Cormorant Garamond',serif; font-weight:600; border-top:2px solid #C5A882;">
                            <span>TOTAL</span><span style="color:#9C7A4A;">PKR
                                <?= number_format($grand_comm,2) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Payment Info -->
                <div
                    style="background:#F8F5EF; border-left:3px solid #C5A882; padding:1rem 1.2rem; margin-bottom:1.5rem;">
                    <div
                        style="font-size:0.58rem; letter-spacing:0.2em; text-transform:uppercase; color:#9C7A4A; margin-bottom:0.5rem;">
                        Make all payments payable to</div>
                    <div style="font-size:0.75rem; white-space:pre-line; color:#2C2A26; line-height:1.6;">
                        <?= htmlspecialchars($owner['bank_details'] ?? '') ?></div>
                </div>

                <!-- Footer -->
                <div
                    style="font-size:0.65rem; color:#6B6560; border-top:1px solid rgba(197,168,130,0.3); padding-top:1rem; letter-spacing:0.08em;">
                    If you have any questions about this invoice, please contact<br>
                    <strong style="color:#2C2A26"><?= htmlspecialchars($owner['full_name']) ?>,
                        <?= htmlspecialchars($owner['phone'] ?? '') ?>,
                        <?= htmlspecialchars($owner['email'] ?? '') ?></strong>
                </div>

            </div>
        </div><!-- #invoice-preview -->

        <?php elseif ($selected_do_id): ?>
        <div class="card" style="display:flex; align-items:center; justify-content:center; min-height:200px;">
            <div class="no-data">Is delivery order ka koi matching contract nahi mila — commission calculate nahi ho
                saki.</div>
        </div>
        <?php elseif ($delivery_list): ?>
        <div class="card" style="display:flex; align-items:center; justify-content:center; min-height:300px;">
            <div class="no-data">← Koi bhi delivery order click karo preview ke liye</div>
        </div>
        <?php else: ?>
        <div class="card" style="display:flex; align-items:center; justify-content:center; min-height:300px;">
            <div class="no-data">← Pehle customer ya supplier select karo</div>
        </div>
        <?php endif; ?>
    </div><!-- right -->
</div><!-- grid -->

<script>
function printInvoice() {
    const element = document.getElementById('invoice-preview');
    const opt = {
        margin: 0.5,
        filename: 'commission-<?= preg_replace('/[^a-zA-Z0-9_-]/', '_', $party_name) ?>-do<?= $selected_do_id ?>-<?= date('Y-m-d') ?>.pdf',
        image: {
            type: 'jpeg',
            quality: 0.98
        },
        html2canvas: {
            scale: 2,
            useCORS: true
        },
        jsPDF: {
            unit: 'in',
            format: 'a4',
            orientation: 'portrait'
        }
    };
    html2pdf().set(opt).from(element).save();
}
</script>

<?php require_once '../includes/footer.php'; ?>