<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();

$pageTitle = 'Commission PDF';
$activePage = 'commission';

$customers = $conn->query("SELECT id, name FROM customers ORDER BY name");
$suppliers = $conn->query("SELECT id, name FROM suppliers ORDER BY name");

$filter_supplier  = (int)($_GET['supplier_id'] ?? 0);
$filter_customer  = (int)($_GET['customer_id'] ?? 0);
$selected_ct_id   = (int)($_GET['contract_id'] ?? 0);  // selected contract
$selected_do_id   = (int)($_GET['do_id'] ?? 0);        // selected delivery (for highlighting)

$party_name   = '';
$party_type   = '';
$contract_list = [];
$delivery_list = [];
$invoice_data  = [];
$grand_comm    = 0;
$contract_row  = null;

// ──────────────────────────────────────────
// Step 1 – both party selects available
// ──────────────────────────────────────────
if ($filter_supplier && $filter_customer) {
    $owner = getOwner();

    $inv_row     = $conn->query("SELECT last_invoice FROM invoice_counter LIMIT 1")->fetch_assoc();
    $invoice_num = ($inv_row['last_invoice'] ?? 14) + 1;

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

    // ── Step 2: Contracts list ──
    $ct_where = "WHERE 1=1";
    if ($filter_supplier) $ct_where .= " AND ct.supplier_id = $filter_supplier";
    if ($filter_customer) $ct_where .= " AND ct.customer_id = $filter_customer";

    $cts = $conn->query("
        SELECT ct.*,
               c.name  AS customer_name,
               s.name  AS supplier_name
        FROM contracts ct
        LEFT JOIN customers c ON ct.customer_id = c.id
        LEFT JOIN suppliers s ON ct.supplier_id = s.id
        $ct_where
        ORDER BY ct.contract_date DESC
    ");
    while ($row = $cts->fetch_assoc()) {
        $contract_list[] = $row;
    }

    // ── Step 3: Deliveries for selected contract ──
    if ($selected_ct_id) {
        // Get contract info
        foreach ($contract_list as $ct) {
            if ($ct['id'] == $selected_ct_id) {
                $contract_row = $ct;
                break;
            }
        }

        // Match deliveries by contract_id OR by supplier+customer combo (for DOs with no contract linked)
        // $ct_sup = (int)($contract_row['supplier_id'] ?? 0);
        // $ct_cus = (int)($contract_row['customer_id'] ?? 0);

        // $do_conditions = ["d.contract_id = $selected_ct_id"];
        // if ($ct_sup && $ct_cus) {
        //     $do_conditions[] = "(d.contract_id IS NULL AND d.supplier_id = $ct_sup AND d.customer_id = $ct_cus)";
        // } elseif ($ct_sup) {
        //     $do_conditions[] = "(d.contract_id IS NULL AND d.supplier_id = $ct_sup)";
        // } elseif ($ct_cus) {
        //     $do_conditions[] = "(d.contract_id IS NULL AND d.customer_id = $ct_cus)";
        // }
        // $do_where_clause = implode(' OR ', $do_conditions);

      $dos = $conn->query("
    SELECT d.*,
           c.name AS customer_name,
           s.name AS supplier_name,
           ct.commission_type,
           ct.commission_value,
           ct.qty_unit
    FROM delivery_orders d
    LEFT JOIN customers c ON d.customer_id = c.id
    LEFT JOIN suppliers s ON d.supplier_id = s.id
    LEFT JOIN contracts ct ON d.contract_id = ct.id
    WHERE d.contract_id = $selected_ct_id
    ORDER BY d.do_date ASC, d.id ASC
");

        while ($row = $dos->fetch_assoc()) {
            // Fallback commission from contract_row if not linked
            if (empty($row['commission_type']) && $contract_row) {
                $row['commission_type']  = $contract_row['commission_type'];
                $row['commission_value'] = $contract_row['commission_value'];
            }
            if (empty($row['qty_unit']) && $contract_row) {
                $row['qty_unit'] = $contract_row['qty_unit'];
            }
            $delivery_list[] = $row;
        }

        // ── Build invoice: ALL deliveries of this contract ──
        foreach ($delivery_list as $d) {
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
                'id'          => $d['id'],
                'date'        => $d['do_date'],
                'description' => $desc,
                'qty'         => $qty,
                'qty_unit'    => $d['qty_unit'] ?? '',
                'rate'        => (float)$d['rate'],
                'debit'       => $debit,
                'comm_label'  => $comm_label,
                'commission'  => $commission,
                'type'        => $d['type'],
            ];
            $grand_comm += $commission;
        }
    }
}

require_once '../includes/header.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div class="page-header">
    <h1>Commission PDF Generator</h1>
</div>

<div style="display:grid; grid-template-columns:340px 1fr; gap:1.5rem; align-items:start">

    <!-- ═══════════════ LEFT PANEL ═══════════════ -->
    <div>

        <!-- Party Selector -->
        <div class="card" style="margin-bottom:1rem">
            <div class="card-header">
                <div class="card-title">① Select Party</div>
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
                        style="text-align:center; font-size:0.65rem; color:var(--ash); margin:0.4rem 0; letter-spacing:0.15em">
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
                    <!-- Preserve contract selection when party changes -->
                    <?php if ($selected_ct_id): ?>
                    <input type="hidden" name="contract_id" value="<?= $selected_ct_id ?>">
                    <?php endif; ?>
                    <a href="commission_pdf.php" class="btn btn-secondary"
                        style="width:100%; justify-content:center">Clear</a>
                </form>
            </div>
        </div>

        <!-- ── Contracts List ── -->
        <?php if ($filter_supplier && $filter_customer): ?>
        <div class="card" style="margin-bottom:1rem">
            <div class="card-header">
                <div class="card-title">② Select Contract</div>
            </div>
            <?php if ($contract_list): ?>
            <div class="card-body" style="padding:0">
                <div style="overflow:auto; max-height:280px;">
                    <?php foreach ($contract_list as $ct): ?>
                    <?php
                        $isActive = ($ct['id'] == $selected_ct_id);
                        $ct_url   = "commission_pdf.php?supplier_id=$filter_supplier&customer_id=$filter_customer&contract_id={$ct['id']}";
                        // Count deliveries linked to this contract
                        $do_count = $conn->query("SELECT COUNT(*) as cnt FROM delivery_orders WHERE contract_id={$ct['id']}")->fetch_assoc()['cnt'];
                        // Sum commission for preview
                        $ct_dos = $conn->query("SELECT d.qty, d.debit, d.rate FROM delivery_orders d WHERE d.contract_id={$ct['id']}");
                        $ct_total_comm = 0;
                        while ($cdrow = $ct_dos->fetch_assoc()) {
                            if ($ct['commission_type'] === 'percentage') {
                                $ct_total_comm += (float)$cdrow['debit'] * ($ct['commission_value'] / 100);
                            } elseif ($ct['commission_type'] === 'unit_based') {
                                $ct_total_comm += (float)$cdrow['qty'] * $ct['commission_value'];
                            }
                        }
                    ?>
                    <a href="<?= $ct_url ?>" style="
                        display:block;
                        padding:0.75rem 1rem;
                        border-bottom:1px solid var(--border);
                        text-decoration:none;
                        background:<?= $isActive ? 'var(--cream)' : 'white' ?>;
                        border-left:3px solid <?= $isActive ? 'var(--bronze)' : 'transparent' ?>;
                    ">
                        <div
                            style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.2rem;">
                            <span
                                style="font-size:0.68rem; color:var(--ash)"><?= date('d/m/Y', strtotime($ct['contract_date'])) ?></span>
                            <span
                                style="font-size:0.65rem; background:var(--cream); color:var(--bronze); padding:1px 6px; border-radius:8px; border:1px solid rgba(197,168,130,0.4);"><?= $do_count ?>
                                DO<?= $do_count!=1?'s':'' ?></span>
                        </div>
                        <div
                            style="font-size:0.78rem; font-weight:500; color:var(--dark); margin-bottom:0.2rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <?= htmlspecialchars(strip_tags($ct['description'] ?? '—')) ?>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:0.7rem; color:var(--ash);">
                                <?= number_format((float)$ct['qty'], 0) ?>
                                <?= htmlspecialchars($ct['qty_unit'] ?? '') ?>
                                &nbsp;·&nbsp; PKR <?= number_format((float)$ct['rate'], 2) ?>
                            </span>
                            <?php if ($ct_total_comm > 0): ?>
                            <span style="font-size:0.68rem; color:#2a7a4a; font-weight:600;">
                                PKR <?= number_format($ct_total_comm, 0) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($ct['commission_type']): ?>
                        <div style="font-size:0.62rem; color:var(--bronze); margin-top:0.2rem;">
                            <?php if ($ct['commission_type']==='percentage'): ?>
                            <?= $ct['commission_value'] ?>% commission
                            <?php else: ?>
                            PKR <?= number_format($ct['commission_value'],2) ?>/unit
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="card-body" style="text-align:center; padding:1.5rem; color:var(--ash); font-size:0.8rem;">
                No contracts found for this party.
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ── Delivery Orders List ── -->
        <?php if ($selected_ct_id && $delivery_list): ?>
        <div class="card">
            <div class="card-header">
                <div class="card-title">③ Deliveries</div>
            </div>
            <div class="card-body" style="padding:0">
                <div style="overflow:auto; max-height:320px;">
                    <?php foreach ($delivery_list as $d): ?>
                    <?php
                        $isActive  = ($d['id'] == $selected_do_id);
                        $do_debit  = (float)$d['debit'];
                        // Per-DO commission preview
                        if ($d['commission_type'] === 'percentage') {
                            $prev_comm  = $do_debit * ($d['commission_value'] / 100);
                        } elseif ($d['commission_type'] === 'unit_based') {
                            $prev_comm  = (float)$d['qty'] * $d['commission_value'];
                        } else {
                            $prev_comm = 0;
                        }
                        // Clicking a DO just highlights it (invoice always shows full contract)
                        $do_url = "commission_pdf.php?supplier_id=$filter_supplier&customer_id=$filter_customer&contract_id=$selected_ct_id&do_id={$d['id']}";
                    ?>
                    <a href="<?= $do_url ?>" style="
                        display:block;
                        padding:0.65rem 1rem;
                        border-bottom:1px solid var(--border);
                        text-decoration:none;
                        background:<?= $isActive ? 'var(--cream)' : 'white' ?>;
                        border-left:3px solid <?= $isActive ? 'var(--bronze)' : 'transparent' ?>;
                    ">
                        <div
                            style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.15rem;">
                            <span
                                style="font-size:0.67rem; color:var(--ash)"><?= date('d/m/Y', strtotime($d['do_date'])) ?></span>
                            <span class="badge badge-<?= $d['type'] ?>"><?= $d['type'] ?></span>
                        </div>
                        <div
                            style="font-size:0.75rem; font-weight:500; color:var(--dark); margin-bottom:0.15rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <?= htmlspecialchars(strip_tags($d['description'] ?? '—')) ?>
                        </div>
                        <div style="display:flex; justify-content:space-between;">
                            <span style="font-size:0.68rem; color:var(--bronze); font-weight:600;">
                                PKR <?= number_format($do_debit, 2) ?>
                                <span
                                    style="font-weight:400; color:var(--ash);">&nbsp;<?= number_format((float)$d['qty'], 2) ?>
                                    <?= htmlspecialchars($d['qty_unit'] ?? '') ?></span>
                            </span>
                            <?php if ($prev_comm > 0): ?>
                            <span style="font-size:0.67rem; color:#2a7a4a; font-weight:600;">PKR
                                <?= number_format($prev_comm, 2) ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <!-- Summary bar -->
                <?php
                    $total_debit = array_sum(array_column($delivery_list, 'debit'));
                    $total_qty   = array_sum(array_column($delivery_list, 'qty'));
                ?>
                <div
                    style="padding:0.6rem 1rem; background:var(--cream); border-top:1px solid var(--border); font-size:0.7rem; display:flex; justify-content:space-between; color:var(--dark);">
                    <span><strong><?= count($delivery_list) ?></strong> deliveries · <?= number_format($total_qty,2) ?>
                        units</span>
                    <span>Total Debit: <strong>PKR <?= number_format($total_debit,2) ?></strong></span>
                </div>
            </div>
        </div>
        <?php elseif ($selected_ct_id): ?>
        <div class="card">
            <div class="card-body" style="text-align:center; padding:1.5rem; color:var(--ash); font-size:0.8rem;">
                No delivery orders found for this contract.
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /left -->

    <!-- ═══════════════ RIGHT: Invoice Preview ═══════════════ -->
    <div>
        <?php if ($invoice_data && $selected_ct_id): ?>

        <div style="display:flex; justify-content:flex-end; margin-bottom:1rem; gap:0.75rem; align-items:center;">
            <span style="font-size:0.75rem; color:var(--ash);">
                <?= count($invoice_data) ?> deliveries · Total Commission:
                <strong style="color:var(--bronze);">PKR <?= number_format($grand_comm,2) ?></strong>
            </span>
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
                            <?= htmlspecialchars($owner['city']) ?>
                        </div>
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

                <div style="height:2px; background:linear-gradient(90deg,#C5A882,transparent); margin-bottom:1.5rem;">
                </div>

                <!-- Bill To + Contract Ref -->
                <div style="display:flex; justify-content:space-between; margin-bottom:1.5rem; align-items:flex-start;">
                    <div>
                        <div
                            style="font-size:0.58rem; letter-spacing:0.25em; text-transform:uppercase; color:#9C7A4A; margin-bottom:0.4rem;">
                            Bill To</div>
                        <div style="font-size:1rem; font-weight:500; color:#2C2A26;">
                            <?= htmlspecialchars($party_name) ?></div>
                        <div
                            style="font-size:0.7rem; color:#6B6560; margin-top:0.2rem; text-transform:uppercase; letter-spacing:0.1em;">
                            <?php if ($party_type==='both'): ?>Customer & Supplier
                            <?php elseif ($party_type==='supplier'): ?>Supplier
                            <?php else: ?>Customer<?php endif; ?>
                        </div>
                    </div>
                    <?php if ($contract_row): ?>
                    <div
                        style="text-align:right; background:#F8F5EF; padding:0.6rem 0.9rem; border-radius:4px; border:1px solid rgba(197,168,130,0.3);">
                        <div
                            style="font-size:0.58rem; letter-spacing:0.2em; text-transform:uppercase; color:#9C7A4A; margin-bottom:0.3rem;">
                            Contract Ref</div>
                        <div style="font-size:0.7rem; color:#2C2A26; font-weight:500;">
                            #<?= $contract_row['id'] ?> —
                            <?= date('d/m/Y', strtotime($contract_row['contract_date'])) ?>
                        </div>
                        <div
                            style="font-size:0.68rem; color:#6B6560; margin-top:0.15rem; max-width:200px; text-align:right;">
                            <?= htmlspecialchars(strip_tags($contract_row['description'] ?? '')) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Table -->
                <table style="width:100%; border-collapse:collapse; font-size:0.72rem; margin-bottom:1.5rem;">
                    <thead>
                        <tr style="background:#EDE8DF; border-bottom:1px solid rgba(197,168,130,0.4);">
                            <th
                                style="padding:0.6rem 0.8rem; text-align:left; font-size:0.58rem; font-weight:400; letter-spacing:0.2em; text-transform:uppercase; color:#6B6560;">
                                #</th>
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
                        <?php $row_num = 1; foreach ($invoice_data as $item):
                            $isHighlighted = ($item['id'] == $selected_do_id);
                        ?>
                        <tr
                            style="border-bottom:1px solid rgba(197,168,130,0.15); <?= $isHighlighted ? 'background:#FFF8EE;' : '' ?>">
                            <td style="padding:0.6rem 0.8rem; color:#9C7A4A; font-size:0.65rem;"><?= $row_num++ ?></td>
                            <td style="padding:0.6rem 0.8rem;">
                                <?= htmlspecialchars($item['description']) ?>
                                <div style="font-size:0.6rem; color:#9C7A4A; margin-top:2px;">
                                    <?= date('d/m/Y', strtotime($item['date'])) ?>
                                    &nbsp;<span class="badge badge-<?= $item['type'] ?>"
                                        style="font-size:0.55rem;"><?= $item['type'] ?></span>
                                </div>
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
                    <div style="min-width:280px;">
                        <?php
                            $total_debit_inv = array_sum(array_column($invoice_data, 'debit'));
                            $total_qty_inv   = array_sum(array_column($invoice_data, 'qty'));
                        ?>
                        <div
                            style="display:flex; justify-content:space-between; padding:0.35rem 0; font-size:0.7rem; border-bottom:1px solid rgba(197,168,130,0.2); color:#6B6560;">
                            <span>Total Qty</span><span><?= number_format($total_qty_inv,2) ?>
                                <?= htmlspecialchars($invoice_data[0]['qty_unit'] ?? '') ?></span>
                        </div>
                        <div
                            style="display:flex; justify-content:space-between; padding:0.35rem 0; font-size:0.7rem; border-bottom:1px solid rgba(197,168,130,0.2); color:#6B6560;">
                            <span>Total Debit</span><span>PKR <?= number_format($total_debit_inv,2) ?></span>
                        </div>
                        <div
                            style="display:flex; justify-content:space-between; padding:0.35rem 0; font-size:0.72rem; border-bottom:1px solid rgba(197,168,130,0.2);">
                            <span style="color:#6B6560;">Commission Subtotal</span><span>PKR
                                <?= number_format($grand_comm,2) ?></span>
                        </div>
                        <div
                            style="display:flex; justify-content:space-between; padding:0.35rem 0; font-size:0.72rem; border-bottom:1px solid rgba(197,168,130,0.2);">
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

        <?php elseif ($selected_ct_id && empty($delivery_list)): ?>
        <div class="card" style="display:flex; align-items:center; justify-content:center; min-height:200px;">
            <div class="no-data">No delivery orders found for this contract — PDF cannot be generated.</div>
        </div>
        <?php elseif ($filter_supplier || $filter_customer): ?>
        <div class="card" style="display:flex; align-items:center; justify-content:center; min-height:300px;">
            <div class="no-data" style="text-align:center;">
                <div style="font-size:2rem; margin-bottom:0.5rem;">①</div>
                Please select both Customer and Supplier
            </div>
        </div>

        <?php elseif ($contract_list): ?>
        <div class="card" style="display:flex; align-items:center; justify-content:center; min-height:300px;">
            <div class="no-data" style="text-align:center;">
                <div style="font-size:2rem; margin-bottom:0.5rem;">②</div>
                Select any contract — commission will be calculated for all its deliveries
            </div>
        </div>

        <?php else: ?>
        <div class="card" style="display:flex; align-items:center; justify-content:center; min-height:300px;">
            <div class="no-data" style="text-align:center;">
                <div style="font-size:2rem; margin-bottom:0.5rem;">①</div>
                Please select a customer or supplier first
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /right -->
</div><!-- grid -->

<script>
function printInvoice() {
    const element = document.getElementById('invoice-preview');
    const opt = {
        margin: 0.5,
        filename: 'commission-<?= preg_replace('/[^a-zA-Z0-9_-]/', '_', $party_name) ?>-ct<?= $selected_ct_id ?>-<?= date('Y-m-d') ?>.pdf',
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