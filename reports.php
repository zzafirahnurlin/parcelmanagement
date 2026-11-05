<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('Admin');

$from = $_GET['from'] ?? date('Y-m-01'); $to = $_GET['to'] ?? date('Y-m-d'); $statusF = $_GET['status'] ?? '';
if (!strtotime($from) || !strtotime($to)) { $from = date('Y-m-01'); $to = date('Y-m-d'); }
$where = "DATE(p.arrivalDate) BETWEEN ? AND ?";
if ($statusF === 'Collected') $where .= " AND p.status='Collected'";
elseif ($statusF === 'Uncollected') $where .= " AND p.status<>'Collected' AND DATEDIFF(NOW(),p.arrivalDate)<=7";
elseif ($statusF === 'Overdue') $where .= " AND p.status<>'Collected' AND DATEDIFF(NOW(),p.arrivalDate)>7";

$st = $conn->prepare("SELECT p.parcelID, p.trackingNo AS trackingRef, p.recipientUserID, p.size, p.status, p.arrivalDate,
        COALESCE(p.collectedAt, p.collectedDate) AS collectedAt,
        COALESCE(NULLIF(p.recipientName,''), NULLIF(p.recipientLabel,''), u.name, '—') AS recipientName,
        COALESCE(u.category, '—') AS category,
        (SELECT SUM(COALESCE(NULLIF(total,0),amount)) FROM payments py WHERE py.parcelID=p.parcelID) AS paid,
        (SELECT SUM(CASE WHEN paymentType='Fine' THEN amount ELSE COALESCE(fineAmount,0) END) FROM payments py WHERE py.parcelID=p.parcelID) AS fine
    FROM parcels p LEFT JOIN users u ON u.userID=p.recipientUserID WHERE $where ORDER BY p.arrivalDate DESC");
$st->bind_param('ss', $from, $to); $st->execute(); $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();

$sum = ['total'=>count($rows), 'collected'=>0, 'uncollected'=>0, 'overdue'=>0, 'fees'=>0.0, 'fines'=>0.0]; $byCat = [];
foreach ($rows as $r) {
    $s = effectiveStatus($r); $sum[$s === 'Pending' ? 'uncollected' : strtolower($s)]++;
    $sum['fees'] += (float)($r['paid'] ?? 0); $sum['fines'] += (float)($r['fine'] ?? 0);
    $byCat[$r['category']] = ($byCat[$r['category']] ?? 0) + 1;
}
arsort($byCat);

if (isset($_GET['pdf'])) {
    require_once __DIR__ . '/../vendor/autoload.php';
    logAudit($conn, 'GENERATE_REPORT', null, "PDF $from to $to" . ($statusF ? " ($statusF)" : ''));
    ob_start(); ?>
    <html><head><meta charset="UTF-8"><style>
      body{font-family:DejaVu Sans,sans-serif;font-size:10.5px;color:#17191D} h1{font-size:18px;margin:0} .sub{color:#666;margin:4px 0 14px}
      table{width:100%;border-collapse:collapse} th{background:#2B2F36;color:#fff;text-align:left;padding:6px;font-size:9.5px;text-transform:uppercase} td{padding:6px;border-bottom:1px solid #ddd}
      .k{display:inline-block;width:23%;border:1px solid #ddd;padding:8px;margin:0 1% 12px 0} .k b{display:block;font-size:16px} .r{text-align:right} .mono{font-family:DejaVu Sans Mono,monospace;font-size:9.5px}
    </style></head><body>
    <h1>Koperasi KPMB — Parcel Operations Report</h1>
    <div class="sub">Period <?= e($from) ?> to <?= e($to) ?><?= $statusF ? " · Status: $statusF" : '' ?> · Generated <?= date('j M Y, H:i') ?> by <?= e($_SESSION['name'] ?? currentUserID()) ?></div>
    <div class="k">Total parcels<b><?= $sum['total'] ?></b></div><div class="k">Collected<b><?= $sum['collected'] ?></b></div><div class="k">Uncollected / Overdue<b><?= $sum['uncollected'] ?> / <?= $sum['overdue'] ?></b></div><div class="k">Fees collected<b><?= rm($sum['fees']) ?></b></div>
    <table><thead><tr><th>Parcel ID</th><th>Tracking / Ref</th><th>Recipient</th><th>Category</th><th>Size</th><th>Arrived</th><th>Collected</th><th>Status</th><th class="r">Paid</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?><tr><td class="mono"><?= e($r['parcelID']) ?></td><td class="mono"><?= e($r['trackingRef']) ?></td><td><?= e($r['recipientName']) ?><?= $r['recipientUserID'] ? ' (' . e($r['recipientUserID']) . ')' : '' ?></td><td><?= e($r['category']) ?></td><td><?= e($r['size']) ?></td>
      <td><?= date('d/m/Y', strtotime($r['arrivalDate'])) ?></td><td><?= $r['collectedAt'] ? date('d/m/Y', strtotime($r['collectedAt'])) : '—' ?></td><td><?= effectiveStatus($r) ?></td><td class="r"><?= $r['paid'] !== null ? rm($r['paid']) : '—' ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <p style="margin-top:12px;color:#666">Fines included in fees: <?= rm($sum['fines']) ?>. By category: <?php foreach ($byCat as $c => $n) echo e($c) . " $n; "; ?> Fee rules: RM1 small, RM3 big, +RM3 fine after 7 days.</p>
    </body></html>
    <?php $html = ob_get_clean();
    $pdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]); $pdf->loadHtml($html); $pdf->setPaper('A4', 'landscape'); $pdf->render();
    $pdf->stream("parcel_report_{$from}_to_{$to}.pdf", ['Attachment' => true]); exit;
}

$qs = http_build_query(['from'=>$from, 'to'=>$to, 'status'=>$statusF]);
$pageTitle = 'Reports'; $pageSubtitle = 'Daily, weekly or monthly operations summary. Download as PDF for the koperasi board.';
$pageActions = '<a class="btn-x primary" href="?' . $qs . '&pdf=1"><i class="bi bi-file-earmark-pdf"></i> Download PDF</a>';
require_once __DIR__ . '/../includes/layouttop.php';
?>
<div class="card-x" style="margin-bottom:20px"><div class="bd" style="padding:16px 22px">
  <form method="get" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
    <div class="field" style="margin:0"><label>From</label><input type="date" name="from" value="<?= e($from) ?>"></div>
    <div class="field" style="margin:0"><label>To</label><input type="date" name="to" value="<?= e($to) ?>"></div>
    <div class="field" style="margin:0"><label>Status</label><select name="status"><option value="">All</option><?php foreach (['Collected','Uncollected','Overdue'] as $o): ?><option <?= $statusF === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div>
    <button class="btn-x primary"><i class="bi bi-funnel"></i> Apply</button>
    <a class="btn-x" href="?from=<?= date('Y-m-d') ?>&to=<?= date('Y-m-d') ?>">Today</a>
    <a class="btn-x" href="?from=<?= date('Y-m-d', strtotime('monday this week')) ?>&to=<?= date('Y-m-d') ?>">This week</a>
    <a class="btn-x" href="?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>">This month</a>
  </form></div></div>
<div class="grid g4">
  <div class="card-x kpi info"><div class="row"><span class="lbl">Parcels</span><span class="ico"><i class="bi bi-box-seam"></i></span></div><div class="val"><?= $sum['total'] ?></div><div class="hint">in period</div></div>
  <div class="card-x kpi ok"><div class="row"><span class="lbl">Collected</span><span class="ico"><i class="bi bi-check2-circle"></i></span></div><div class="val"><?= $sum['collected'] ?></div><div class="hint"><?= $sum['total'] ? round($sum['collected'] / $sum['total'] * 100) : 0 ?>% collection rate</div></div>
  <div class="card-x kpi bad"><div class="row"><span class="lbl">Uncollected / overdue</span><span class="ico"><i class="bi bi-exclamation-triangle"></i></span></div><div class="val"><?= $sum['uncollected'] ?> <small>/ <?= $sum['overdue'] ?></small></div><div class="hint">still in storage</div></div>
  <div class="card-x kpi warn"><div class="row"><span class="lbl">Fees collected</span><span class="ico"><i class="bi bi-cash-coin"></i></span></div><div class="val"><small>RM</small><?= number_format($sum['fees'], 2) ?></div><div class="hint">incl. fines <?= rm($sum['fines']) ?></div></div>
</div>
<div class="toolbar mt"><?php foreach ($byCat as $c => $n): ?><span class="pill neutral plain"><?= e($c) ?>: <b><?= $n ?></b></span><?php endforeach; ?></div>
<div class="card-x"><div class="table-wrap"><table class="table-x">
  <thead><tr><th>Parcel ID</th><th>Tracking / Ref</th><th>Recipient</th><th>Category</th><th>Size</th><th>Arrived</th><th>Collected</th><th>Status</th><th class="num">Paid</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="9"><div class="empty"><i class="bi bi-funnel"></i>No parcels in this range</div></td></tr>
  <?php else: foreach ($rows as $r): ?>
    <tr><td class="mono muted"><?= e($r['parcelID']) ?></td><td class="mono"><?= e($r['trackingRef']) ?></td>
        <td><?= e($r['recipientName']) ?><?= $r['recipientUserID'] ? '<br><small class="mono muted">' . e($r['recipientUserID']) . '</small>' : '' ?></td>
        <td><?= e($r['category']) ?></td><td><?= e($r['size']) ?></td>
        <td class="muted"><?= date('j M Y', strtotime($r['arrivalDate'])) ?></td><td class="muted"><?= $r['collectedAt'] ? date('j M Y', strtotime($r['collectedAt'])) : '—' ?></td>
        <td><?= statusPill(effectiveStatus($r)) ?></td><td class="num mono"><?= $r['paid'] !== null ? rm($r['paid']) : '—' ?></td></tr>
  <?php endforeach; endif; ?>
  </tbody></table></div></div>
<?php require_once __DIR__ . '/../includes/layoutbottom.php'; ?>