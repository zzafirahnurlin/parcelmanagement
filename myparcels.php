<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('User', 'Staff');
$me = currentUserID(); $f = $_GET['f'] ?? 'all';

$st = $conn->prepare("SELECT p.parcelID, p.trackingNo, p.size, p.location, p.status, p.arrivalDate, p.collectedAt, p.collectedDate,
                             (SELECT SUM(total) FROM payments py WHERE py.parcelID = p.parcelID) AS paid
                      FROM parcels p WHERE p.recipientUserID=? ORDER BY (p.status='Collected'), p.arrivalDate DESC");
$st->bind_param('s', $me); $st->execute(); $all = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();

$n = ['pending'=>0,'overdue'=>0,'collected'=>0]; $due = 0.0; $rows = [];
foreach ($all as $p) {
    $s = effectiveStatus($p); $n[strtolower($s)]++;
    if ($s !== 'Collected') $due += parcelFee($p['size'], $p['arrivalDate'])['total'];
    if ($f === 'all' || $f === strtolower($s)) $rows[] = $p;
}

$pageTitle = 'My Parcels'; $pageSubtitle = 'Show your pickup QR code at the Koperasi counter to verify ownership and collect your parcels.';
require_once __DIR__ . '/../includes/layouttop.php';
?>
<div class="grid g3">
  <div class="card-x kpi warn"><div class="row"><span class="lbl">Awaiting pickup</span><span class="ico"><i class="bi bi-box-seam"></i></span></div><div class="val"><?= $n['pending'] + $n['overdue'] ?></div><div class="hint">Ready on the cooperative shelves</div></div>
  <div class="card-x kpi ok"><div class="row"><span class="lbl">Collected</span><span class="ico"><i class="bi bi-check2-circle"></i></span></div><div class="val"><?= $n['collected'] ?></div><div class="hint">Successfully picked up</div></div>
  <div class="card-x kpi bad"><div class="row"><span class="lbl">Total balance due</span><span class="ico"><i class="bi bi-cash-coin"></i></span></div><div class="val"><small>RM</small><?= number_format($due, 2) ?></div><div class="hint">Payable in cash at pickup</div></div>
</div>

<div class="toolbar mt">
  <a class="btn-x sm <?= $f==='all'?'primary':'' ?>" href="?f=all">All (<?= count($all) ?>)</a>
  <a class="btn-x sm <?= $f==='pending'?'primary':'' ?>" href="?f=pending">Pending (<?= $n['pending'] ?>)</a>
  <a class="btn-x sm <?= $f==='overdue'?'primary':'' ?>" href="?f=overdue">Overdue (<?= $n['overdue'] ?>)</a>
  <a class="btn-x sm <?= $f==='collected'?'primary':'' ?>" href="?f=collected">Collected (<?= $n['collected'] ?>)</a>
  <div class="spacer"></div>
  <div class="input-icon" style="min-width:260px"><i class="bi bi-search"></i><input class="form-control" type="text" data-filter="#tbl" placeholder="Search tracking, shelf…"></div>
</div>

<div class="card-x"><div class="table-wrap"><table class="table-x" id="tbl">
  <thead><tr><th>Package / tracking</th><th>Arrival date</th><th>Storage &amp; size</th><th>Status</th><th class="num">Fee due</th><th class="actions">Verification</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="6"><div class="empty"><i class="bi bi-inbox"></i>No parcels in this list.</div></td></tr>
  <?php else: foreach ($rows as $p): $done = $p['status']==='Collected'; $fee = parcelFee($p['size'], $p['arrivalDate']); $d = daysSince($p['arrivalDate']); ?>
    <tr>
      <td><b class="mono"><?= e($p['trackingNo']) ?></b><br><small class="muted mono">Ref: <?= e($p['parcelID']) ?></small></td>
      <td><?= date('j M Y', strtotime($p['arrivalDate'])) ?><br><small class="muted"><?= $done ? 'Collected ' . date('j M', strtotime($p['collectedAt'] ?? $p['collectedDate'])) : "$d day(s) stored" ?></small></td>
      <td><span class="pill neutral plain"><?= e($p['size']) ?></span><br><small class="muted">Shelf <b><?= e($p['location'] ?: '—') ?></b></small></td>
      <td><?= statusPill(effectiveStatus($p)) ?></td>
      <td class="num"><b class="mono"><?= $done ? rm($p['paid'] ?? 0) : rm($fee['total']) ?></b><?= (!$done && $fee['fine']) ? '<br><small style="color:var(--bad)">(+RM3 fine)</small>' : ($done ? '<br><small class="muted">paid</small>' : '') ?></td>
      <td class="actions"><?= $done ? '<span class="muted"><i class="bi bi-check2"></i> Cleared</span>' : '<a class="btn-x sm primary" href="'.BASE_URL.'user/viewqr.php?id='.urlencode($p['parcelID']).'"><i class="bi bi-qr-code"></i> View QR</a>' ?></td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody></table></div></div>
<?php require_once __DIR__ . '/../includes/layoutbottom.php'; ?>