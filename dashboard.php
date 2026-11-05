<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$role = currentRole(); $me = currentUserID();

/* ===================== ADMIN ===================== */
if ($role === 'Admin') {
    $n = fn($sql) => (int)$conn->query($sql)->fetch_row()[0];
    $money = fn($sql) => (float)$conn->query($sql)->fetch_row()[0];
    $PAID = "COALESCE(NULLIF(total,0), amount)";
    $FINE = "CASE WHEN paymentType='Fine' THEN amount ELSE COALESCE(fineAmount,0) END";

    $totalParcels = $n("SELECT COUNT(*) FROM parcels");
    $uncollected  = $n("SELECT COUNT(*) FROM parcels WHERE status<>'Collected'");
    $overdue      = $n("SELECT COUNT(*) FROM parcels WHERE status<>'Collected' AND DATEDIFF(NOW(),arrivalDate)>7");
    $today        = $n("SELECT COUNT(*) FROM parcels WHERE DATE(arrivalDate)=CURDATE()");
    $members      = $n("SELECT COUNT(*) FROM users WHERE role='User'");
    $staffN       = $n("SELECT COUNT(*) FROM users WHERE role='Staff'");
    $feesAll      = $money("SELECT COALESCE(SUM($PAID),0) FROM payments");
    $feesMonth    = $money("SELECT COALESCE(SUM($PAID),0) FROM payments WHERE YEAR(paymentDate)=YEAR(NOW()) AND MONTH(paymentDate)=MONTH(NOW())");
    $finesMonth   = $money("SELECT COALESCE(SUM($FINE),0) FROM payments WHERE YEAR(paymentDate)=YEAR(NOW()) AND MONTH(paymentDate)=MONTH(NOW())");

    $inM   = array_column($conn->query("SELECT DATE(arrivalDate) d, COUNT(*) c FROM parcels WHERE arrivalDate>=DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY d")->fetch_all(MYSQLI_ASSOC), 'c', 'd');
    $outM  = array_column($conn->query("SELECT DATE(COALESCE(collectedAt,collectedDate)) d, COUNT(*) c FROM parcels WHERE COALESCE(collectedAt,collectedDate)>=DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY d")->fetch_all(MYSQLI_ASSOC), 'c', 'd');
    $cashM = array_column($conn->query("SELECT DATE(paymentDate) d, SUM($PAID) c FROM payments WHERE paymentDate>=DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY d")->fetch_all(MYSQLI_ASSOC), 'c', 'd');
    $labels = $in = $out = $cash = [];
    for ($i = 6; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("-$i day")); $labels[] = date('D j', strtotime($d)); $in[] = (int)($inM[$d] ?? 0); $out[] = (int)($outM[$d] ?? 0); $cash[] = (float)($cashM[$d] ?? 0); }
    $collectedAll = $totalParcels - $uncollected; $pendingOnly = $uncollected - $overdue;

    $cats   = $conn->query("SELECT COALESCE(u.category,'Not linked') cat, COUNT(*) c FROM parcels p LEFT JOIN users u ON u.userID=p.recipientUserID GROUP BY cat ORDER BY c DESC")->fetch_all(MYSQLI_ASSOC);
    $recent = $conn->query("SELECT a.createdAt, a.action, a.details, a.actorUserID AS staffUserID, u.name AS staffName FROM audit_logs a LEFT JOIN users u ON u.userID=a.actorUserID ORDER BY a.createdAt DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);

    $pageTitle = 'Dashboard'; $pageSubtitle = 'Live overview of parcel operations, fees and staff activity at Koperasi KPMB.';
    $pageActions = '<a class="btn-x" href="'.BASE_URL.'admin/auditlog.php"><i class="bi bi-shield-check"></i> Audit log</a><a class="btn-x primary" href="'.BASE_URL.'admin/reports.php"><i class="bi bi-file-earmark-pdf"></i> Generate report</a>';
    require_once __DIR__ . '/includes/layouttop.php';
    ?>
    <div class="grid g4">
      <div class="card-x kpi info"><div class="row"><span class="lbl">Parcels today</span><span class="ico"><i class="bi bi-box-arrow-in-down"></i></span></div><div class="val"><?= $today ?></div><div class="hint"><?= $totalParcels ?> all time</div></div>
      <div class="card-x kpi warn"><div class="row"><span class="lbl">Uncollected</span><span class="ico"><i class="bi bi-archive"></i></span></div><div class="val"><?= $uncollected ?></div><div class="hint">On the shelves now</div></div>
      <div class="card-x kpi bad"><div class="row"><span class="lbl">Overdue</span><span class="ico"><i class="bi bi-exclamation-triangle"></i></span></div><div class="val"><?= $overdue ?></div><div class="hint">&gt; 7 days · RM3 fine applies</div></div>
      <div class="card-x kpi ok"><div class="row"><span class="lbl">Fees this month</span><span class="ico"><i class="bi bi-cash-stack"></i></span></div><div class="val"><small>RM</small><?= number_format($feesMonth, 2) ?></div><div class="hint">incl. fines <?= rm($finesMonth) ?> · all time <?= rm($feesAll) ?></div></div>
    </div>

    <div class="grid g-main mt">
      <div class="card-x"><div class="hd"><div><h3>Parcel flow — last 7 days</h3><div class="sub">Registered vs collected per day</div></div></div><div class="bd"><canvas id="flow" height="110"></canvas></div></div>
      <div class="card-x"><div class="hd"><div><h3>Current status</h3><div class="sub">All parcels</div></div></div><div class="bd"><canvas id="status" height="180"></canvas></div></div>
    </div>

    <div class="grid g-main mt">
      <div class="card-x">
        <div class="hd"><div><h3>Recent activity</h3><div class="sub">Latest audit log entries</div></div><a class="btn-x sm" href="<?= BASE_URL ?>admin/auditlog.php">Full log</a></div>
        <div class="table-wrap"><table class="table-x"><thead><tr><th>Time</th><th>Staff</th><th>Action</th><th>Details</th></tr></thead><tbody>
        <?php if (!$recent): ?><tr><td colspan="4"><div class="empty"><i class="bi bi-journal"></i>No activity yet</div></td></tr>
        <?php else: foreach ($recent as $r): ?>
          <tr><td class="muted" style="white-space:nowrap"><?= date('j M, H:i', strtotime($r['createdAt'])) ?></td><td><?= e($r['staffName'] ?: $r['staffUserID']) ?></td><td><span class="pill neutral plain"><?= e(str_replace('_', ' ', $r['action'])) ?></span></td><td class="muted"><?= e($r['details']) ?></td></tr>
        <?php endforeach; endif; ?></tbody></table></div>
      </div>
      <div style="display:flex;flex-direction:column;gap:20px">
        <div class="card-x"><div class="hd"><h3>Cash collected — last 7 days</h3></div><div class="bd"><canvas id="cash" height="130"></canvas></div></div>
        <div class="card-x"><div class="hd"><div><h3>Parcels by member category</h3><div class="sub">Student, lecturer, cafeteria &amp; support staff</div></div></div>
          <div class="bd"><?php $max = max(1, max(array_column($cats, 'c') ?: [1])); foreach ($cats as $c): ?>
            <div style="margin-bottom:12px"><div style="display:flex;justify-content:space-between;font-size:13px"><span><?= e($c['cat']) ?></span><b><?= (int)$c['c'] ?></b></div>
            <div style="height:6px;background:var(--surface-2);border-radius:99px;margin-top:5px"><div style="height:6px;width:<?= round($c['c'] / $max * 100) ?>%;background:var(--accent-2);border-radius:99px"></div></div></div>
          <?php endforeach; if (!$cats): ?><div class="empty" style="padding:16px"><i class="bi bi-pie-chart"></i>No data yet</div><?php endif; ?></div></div>
        <div class="card-x"><div class="hd"><h3>Quick links</h3></div><div class="bd" style="display:grid;gap:10px">
          <a class="tile" href="<?= BASE_URL ?>admin/manageusers.php"><span class="ico"><i class="bi bi-people"></i></span><div><b>Manage accounts</b><span><?= $members ?> members · <?= $staffN ?> staff</span></div><i class="bi bi-arrow-right go"></i></a>
          <a class="tile" href="<?= BASE_URL ?>reminder.php"><span class="ico"><i class="bi bi-megaphone"></i></span><div><b>Reminder engine</b><span>Day 3 / 7 / 14 notices</span></div><i class="bi bi-arrow-right go"></i></a>
        </div></div>
      </div>
    </div>
    <?php ob_start(); ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif"; Chart.defaults.color = '#8A8F99';
    const grid = { color:'#EFEBE3' }, noGrid = { display:false };
    new Chart(document.getElementById('flow'), { type:'bar', data:{ labels:<?= json_encode($labels) ?>, datasets:[
      { label:'Registered', data:<?= json_encode($in) ?>, backgroundColor:'#2B2F36', borderRadius:6 },
      { label:'Collected',  data:<?= json_encode($out) ?>, backgroundColor:'#B08D57', borderRadius:6 } ] },
      options:{ plugins:{ legend:{ position:'bottom' } }, scales:{ x:{ grid:noGrid }, y:{ grid, border:{ display:false }, ticks:{ precision:0 } } } } });
    new Chart(document.getElementById('status'), { type:'doughnut', data:{ labels:['Collected','Pending','Overdue'], datasets:[{ data:[<?= $collectedAll ?>,<?= $pendingOnly ?>,<?= $overdue ?>], backgroundColor:['#15803D','#B45309','#B91C1C'], borderWidth:0 }] },
      options:{ cutout:'72%', plugins:{ legend:{ position:'bottom' } } } });
    new Chart(document.getElementById('cash'), { type:'line', data:{ labels:<?= json_encode($labels) ?>, datasets:[{ label:'RM', data:<?= json_encode($cash) ?>, borderColor:'#B08D57', backgroundColor:'rgba(176,141,87,.15)', fill:true, tension:.35, pointRadius:3 }] },
      options:{ plugins:{ legend:{ display:false } }, scales:{ x:{ grid:noGrid }, y:{ grid, border:{ display:false } } } } });
    </script>
    <?php $extraScripts = ob_get_clean(); require_once __DIR__ . '/includes/layoutbottom.php'; exit;
}

/* ===================== STAFF ===================== */
if ($role === 'Staff') {
    $intakeToday = (int)$conn->query("SELECT COUNT(*) FROM parcels WHERE DATE(arrivalDate)=CURDATE()")->fetch_row()[0];
    $pending     = (int)$conn->query("SELECT COUNT(*) FROM parcels WHERE status<>'Collected' AND DATEDIFF(NOW(),arrivalDate)<=7")->fetch_row()[0];
    $overdue     = (int)$conn->query("SELECT COUNT(*) FROM parcels WHERE status<>'Collected' AND DATEDIFF(NOW(),arrivalDate)>7")->fetch_row()[0];
    $cleared     = (int)$conn->query("SELECT COUNT(*) FROM parcels WHERE status='Collected' AND DATE(COALESCE(collectedAt,collectedDate))=CURDATE()")->fetch_row()[0];
    $recent = $conn->query("SELECT parcelID, trackingNo, recipientName, recipientLabel, size, status, arrivalDate FROM parcels ORDER BY arrivalDate DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);
    $watch  = $conn->query("SELECT parcelID, trackingNo, recipientName, recipientLabel, arrivalDate FROM parcels WHERE status<>'Collected' AND DATEDIFF(NOW(),arrivalDate)>7 ORDER BY arrivalDate ASC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

    $pageTitle    = 'Dashboard';
    $pageSubtitle = 'Record incoming parcels, verify member passes, and record payments.';
    $pageActions  = '<a class="btn-x" href="'.BASE_URL.'staff/collectparcel.php"><i class="bi bi-qr-code-scan"></i> Collect &amp; Pay</a>
                     <a class="btn-x primary" href="'.BASE_URL.'staff/registerparcel.php"><i class="bi bi-plus-lg"></i> Register Parcel</a>';
    require_once __DIR__ . '/includes/layouttop.php';
    ?>
    <div class="grid g4">
      <div class="card-x kpi info"><div class="row"><span class="lbl">Intake today</span><span class="ico"><i class="bi bi-box-arrow-in-down"></i></span></div><div class="val"><?= $intakeToday ?></div><div class="hint">Registered since 00:00</div></div>
      <div class="card-x kpi warn"><div class="row"><span class="lbl">Pending pickup</span><span class="ico"><i class="bi bi-hourglass-split"></i></span></div><div class="val"><?= $pending ?></div><div class="hint">Within 7 days</div></div>
      <div class="card-x kpi bad"><div class="row"><span class="lbl">Overdue &gt; 7 days</span><span class="ico"><i class="bi bi-exclamation-triangle"></i></span></div><div class="val"><?= $overdue ?></div><div class="hint"><b>RM3 fine</b> applies on collection</div></div>
      <div class="card-x kpi ok"><div class="row"><span class="lbl">Cleared today</span><span class="ico"><i class="bi bi-check2-circle"></i></span></div><div class="val"><?= $cleared ?></div><div class="hint">Collected &amp; paid</div></div>
    </div>

    <div class="grid g-main mt">
      <div class="card-x">
        <div class="hd"><div><h3>Recently registered</h3><div class="sub">Latest parcels entered at the counter</div></div>
          <a class="btn-x sm" href="<?= BASE_URL ?>staff/uncollectedparcels.php">View inventory</a></div>
        <div class="table-wrap"><table class="table-x">
          <thead><tr><th>Reference</th><th>Recipient</th><th>Size</th><th>Arrived</th><th>Status</th></tr></thead>
          <tbody>
          <?php if (!$recent): ?><tr><td colspan="5"><div class="empty"><i class="bi bi-inbox"></i>No parcels registered yet</div></td></tr>
          <?php else: foreach ($recent as $p): ?>
            <tr><td class="mono"><a href="<?= BASE_URL ?>staff/collectparcel.php?q=<?= e($p['trackingNo']) ?>"><?= e($p['trackingNo']) ?></a></td>
                <td><?= e($p['recipientName'] ?: $p['recipientLabel']) ?></td><td><span class="pill neutral plain"><?= e($p['size']) ?></span></td>
                <td class="muted"><?= date('j M, H:i', strtotime($p['arrivalDate'])) ?></td><td><?= statusPill(effectiveStatus($p)) ?></td></tr>
          <?php endforeach; endif; ?>
          </tbody></table></div>
      </div>
      <div style="display:flex;flex-direction:column;gap:20px">
        <div class="card-x"><div class="hd"><h3>Quick actions</h3></div>
          <div class="bd" style="display:grid;gap:10px">
            <a class="tile" href="<?= BASE_URL ?>staff/registerparcel.php"><span class="ico"><i class="bi bi-plus-square"></i></span><div><b>Register parcel</b><span>Generate TRK reference &amp; QR</span></div><i class="bi bi-arrow-right go"></i></a>
            <a class="tile" href="<?= BASE_URL ?>staff/collectparcel.php"><span class="ico"><i class="bi bi-qr-code-scan"></i></span><div><b>Collect &amp; pay</b><span>Verify QR, record RM1 / RM3</span></div><i class="bi bi-arrow-right go"></i></a>
            <a class="tile" href="<?= BASE_URL ?>reminder.php"><span class="ico"><i class="bi bi-megaphone"></i></span><div><b>Run reminder engine</b><span>Notify Day 3 / 7 / 14</span></div><i class="bi bi-arrow-right go"></i></a>
          </div></div>
        <div class="card-x"><div class="hd"><div><h3>Needs attention</h3><div class="sub">Oldest uncollected parcels</div></div></div>
          <div class="bd" style="padding-top:8px;padding-bottom:8px">
            <?php if (!$watch): ?><div class="empty" style="padding:24px"><i class="bi bi-emoji-smile"></i>Nothing overdue — great.</div>
            <?php else: ?><ul class="timeline"><?php foreach ($watch as $w): ?>
              <li><span class="dot" style="background:var(--bad)"></span><div><b class="mono"><?= e($w['trackingNo']) ?></b> · <?= e($w['recipientName'] ?: $w['recipientLabel']) ?><small><?= daysSince($w['arrivalDate']) ?> days in storage · fine RM 3.00</small></div></li>
            <?php endforeach; ?></ul><?php endif; ?>
          </div></div>
        <div class="card-x"><div class="hd"><h3>Fee schedule</h3></div>
          <div class="bd"><dl class="kv"><dt>Small parcel</dt><dd>RM 1.00</dd><dt>Big parcel</dt><dd>RM 3.00</dd><dt>Late fine</dt><dd>+ RM 3.00 after 7 days</dd></dl></div></div>
      </div>
    </div>
    <?php require_once __DIR__ . '/includes/layoutbottom.php'; exit;
}

/* ===================== USER ===================== */
$st = $conn->prepare("SELECT parcelID, trackingNo, size, location, status, arrivalDate FROM parcels WHERE recipientUserID=? ORDER BY arrivalDate DESC");
$st->bind_param('s', $me); $st->execute(); $all = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();
$myPending = 0; $myCollected = 0; $myFees = 0.0; $myOverdue = 0;
foreach ($all as $p) {
    if ($p['status'] === 'Collected') $myCollected++;
    else { $myPending++; $myFees += parcelFee($p['size'], $p['arrivalDate'])['total']; if (daysSince($p['arrivalDate']) > 7) $myOverdue++; }
}
$myParcels = array_slice($all, 0, 5);
$st = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE userID=? AND isRead=0"); $st->bind_param('s', $me); $st->execute(); $unreadN = (int)$st->get_result()->fetch_row()[0]; $st->close();

$pageTitle    = 'Dashboard';
$pageSubtitle = 'Track your parcels at Koperasi KPMB and show your QR at the counter.';
$pageActions  = '<a class="btn-x" href="'.BASE_URL.'user/notifications.php"><i class="bi bi-bell"></i> Notifications' . ($unreadN ? ' <span class="pill bad plain" style="margin-left:4px">' . $unreadN . '</span>' : '') . '</a>
                 <a class="btn-x primary" href="'.BASE_URL.'user/myparcels.php"><i class="bi bi-qr-code"></i> My parcels</a>';
require_once __DIR__ . '/includes/layouttop.php';
?>
<div class="grid g3">
  <div class="card-x kpi warn"><div class="row"><span class="lbl">Ready to collect</span><span class="ico"><i class="bi bi-box-seam"></i></span></div><div class="val"><?= $myPending ?></div><div class="hint"><?= $myOverdue ? "<b>$myOverdue overdue</b> — collect soon" : 'Waiting at the counter' ?></div></div>
  <div class="card-x kpi bad"><div class="row"><span class="lbl">Outstanding fees</span><span class="ico"><i class="bi bi-cash-coin"></i></span></div><div class="val"><small>RM</small><?= number_format($myFees, 2) ?></div><div class="hint">Payable in cash on collection</div></div>
  <div class="card-x kpi ok"><div class="row"><span class="lbl">Collected</span><span class="ico"><i class="bi bi-check2-circle"></i></span></div><div class="val"><?= $myCollected ?></div><div class="hint">All time</div></div>
</div>

<div class="grid g-main mt">
  <div class="card-x">
    <div class="hd"><div><h3>Recent parcels</h3><div class="sub">Open a QR and show it at the koperasi counter</div></div><a class="btn-x sm" href="<?= BASE_URL ?>user/myparcels.php">View all</a></div>
    <div class="table-wrap"><table class="table-x">
      <thead><tr><th>Reference</th><th>Size</th><th>Shelf</th><th>Arrived</th><th>Fee due</th><th>Status</th><th class="actions"></th></tr></thead>
      <tbody>
      <?php if (!$myParcels): ?><tr><td colspan="7"><div class="empty"><i class="bi bi-inbox"></i>No parcels yet</div></td></tr>
      <?php else: foreach ($myParcels as $p): $fee = parcelFee($p['size'], $p['arrivalDate']); $done = $p['status'] === 'Collected'; ?>
        <tr><td class="mono"><?= e($p['trackingNo']) ?></td><td><?= e($p['size']) ?></td><td class="mono"><?= e($p['location'] ?: '—') ?></td>
            <td class="muted"><?= date('j M Y', strtotime($p['arrivalDate'])) ?></td>
            <td class="mono"><?= $done ? '—' : rm($fee['total']) ?></td>
            <td><?= statusPill(effectiveStatus($p)) ?></td>
            <td class="actions"><?php if (!$done): ?><a class="btn-x sm primary" href="<?= BASE_URL ?>user/viewqr.php?id=<?= urlencode($p['parcelID']) ?>"><i class="bi bi-qr-code"></i> Show QR</a><?php endif; ?></td></tr>
      <?php endforeach; endif; ?>
      </tbody></table></div>
  </div>
  <div style="display:flex;flex-direction:column;gap:20px">
    <div class="card-x"><div class="hd"><h3>How collection works</h3></div>
      <div class="bd"><ul class="timeline">
        <li><span class="dot"></span><div>You get a notification when a parcel arrives<small>Check the bell or Notifications page</small></div></li>
        <li><span class="dot"></span><div>Open <b>Show QR</b> for that parcel<small>Staff scan it at the counter</small></div></li>
        <li><span class="dot"></span><div>Pay the fee in cash<small>RM1 small · RM3 big</small></div></li>
        <li><span class="dot" style="background:var(--bad)"></span><div>Collect within 7 days<small>Otherwise an RM3.00 fine is added</small></div></li>
      </ul></div></div>
    <div class="card-x"><div class="hd"><h3>Fee schedule</h3></div>
      <div class="bd"><dl class="kv"><dt>Small parcel</dt><dd>RM 1.00</dd><dt>Big parcel</dt><dd>RM 3.00</dd><dt>Late fine</dt><dd>+ RM 3.00 after 7 days</dd></dl></div></div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/layoutbottom.php'; ?>