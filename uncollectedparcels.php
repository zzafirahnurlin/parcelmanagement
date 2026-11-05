<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('Staff', 'Admin');

$f = $_GET['f'] ?? 'all';
$all = $conn->query("SELECT parcelID, trackingNo, recipientUserID, recipientName, recipientLabel, size, location, arrivalDate, status
                     FROM parcels WHERE status<>'Collected' ORDER BY arrivalDate ASC")->fetch_all(MYSQLI_ASSOC);
$pendingN = 0; $overdueN = 0; $rows = []; $totalDue = 0;
foreach ($all as $r) {
    $d = daysSince($r['arrivalDate']);
    $d > 7 ? $overdueN++ : $pendingN++;
    if ($f === 'overdue' && $d <= 7) continue;
    if ($f === 'pending' && $d > 7) continue;
    $rows[] = $r; $totalDue += parcelFee($r['size'], $r['arrivalDate'])['total'];
}

$pageTitle = 'Uncollected Parcels'; $pageSubtitle = 'Live view of parcels waiting on the shelves. Parcels held over 7 days automatically incur an RM 3.00 penalty fine.';
$pageActions = '<a class="btn-x primary" href="'.BASE_URL.'staff/registerparcel.php"><i class="bi bi-plus-lg"></i> Register Parcel</a>';
require_once __DIR__ . '/../includes/layouttop.php';
?>
<div class="grid g3">
  <div class="card-x kpi info"><div class="row"><span class="lbl">Total in storage</span><span class="ico"><i class="bi bi-archive"></i></span></div><div class="val"><?= count($all) ?></div><div class="hint">Parcels currently on the shelves</div></div>
  <div class="card-x kpi warn"><div class="row"><span class="lbl">Pending ≤ 7 days</span><span class="ico"><i class="bi bi-hourglass-split"></i></span></div><div class="val"><?= $pendingN ?></div><div class="hint">Within normal collection window</div></div>
  <div class="card-x kpi bad"><div class="row"><span class="lbl">Overdue &gt; 7 days</span><span class="ico"><i class="bi bi-exclamation-triangle"></i></span></div><div class="val"><?= $overdueN ?></div><div class="hint"><b>+ RM 3.00</b> fine applied on collection</div></div>
</div>

<div class="toolbar mt">
  <a class="btn-x sm <?= $f === 'all' ? 'primary' : '' ?>" href="?f=all">All (<?= count($all) ?>)</a>
  <a class="btn-x sm <?= $f === 'pending' ? 'primary' : '' ?>" href="?f=pending">Pending (<?= $pendingN ?>)</a>
  <a class="btn-x sm <?= $f === 'overdue' ? 'primary' : '' ?>" href="?f=overdue">Overdue (<?= $overdueN ?>)</a>
  <div class="spacer"></div>
  <span class="pill info plain">Fees receivable <?= rm($totalDue) ?></span>
  <div class="input-icon" style="min-width:280px"><i class="bi bi-search"></i><input class="form-control" type="text" data-filter="#tbl" placeholder="Filter by reference, name, ID, shelf…"></div>
</div>

<div class="card-x"><div class="table-wrap"><table class="table-x" id="tbl">
  <thead><tr><th>Package &amp; ref</th><th>Recipient</th><th>Arrived</th><th>Shelf &amp; size</th><th>Status</th><th class="num">Payable (cash)</th><th class="actions">Action</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="7"><div class="empty"><i class="bi bi-emoji-smile"></i>Nothing here — all collected.</div></td></tr>
  <?php else: foreach ($rows as $r): $fee = parcelFee($r['size'], $r['arrivalDate']); $d = daysSince($r['arrivalDate']);
        $name = $r['recipientName'] ?: $r['recipientLabel']; $alias = ($r['recipientLabel'] && $r['recipientLabel'] !== $name) ? $r['recipientLabel'] : ''; ?>
    <tr>
      <td><b class="mono"><?= e($r['trackingNo']) ?></b><br><small class="muted mono">Ref: <?= e($r['parcelID']) ?></small></td>
      <td><div class="person"><div class="avatar"><?= initials($name ?? '') ?></div><div><?= e($name) ?><small class="mono"><?= e($r['recipientUserID'] ?: 'no account') ?><?= $alias ? ' · alias ' . e($alias) : '' ?></small></div></div></td>
      <td><?= date('j M Y', strtotime($r['arrivalDate'])) ?><br><small class="muted"><?= $d ?> day<?= $d === 1 ? '' : 's' ?> stored</small></td>
      <td><span class="pill neutral plain"><i class="bi bi-geo-alt"></i> <?= e($r['location'] ?: '—') ?></span> <span class="pill neutral plain"><?= e($r['size']) ?></span></td>
      <td><?= statusPill(effectiveStatus($r)) ?></td>
      <td class="num"><b class="mono"><?= rm($fee['total']) ?></b><?= $fee['fine'] ? '<br><small style="color:var(--bad)">incl. RM 3.00 fine</small>' : '' ?></td>
      <td class="actions"><a class="btn-x sm primary" href="<?= BASE_URL ?>staff/collectparcel.php?q=<?= e($r['trackingNo']) ?>"><i class="bi bi-qr-code-scan"></i> Checkout</a></td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody></table></div></div>
<?php require_once __DIR__ . '/../includes/layoutbottom.php'; ?>