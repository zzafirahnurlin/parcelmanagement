<?php
require_once __DIR__ . '/includes/auth.php';
requireRole('Staff', 'Admin');
$summary = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run'])) {
    $rows = $conn->query("SELECT parcelID, trackingNo, recipientUserID, size, arrivalDate, status FROM parcels WHERE status<>'Collected'")->fetch_all(MYSQLI_ASSOC);
    $summary = ['checked'=>count($rows), 'sent'=>0, 'overdue'=>0];
    foreach ($rows as $p) {
        $d = daysSince($p['arrivalDate']);
        $stage = $d >= 14 ? 14 : ($d >= 7 ? 7 : ($d >= 3 ? 3 : 0));
        if ($d > 7 && $p['status'] !== 'Overdue') {
            $su = $conn->prepare("UPDATE parcels SET status='Overdue' WHERE parcelID=?"); $su->bind_param('s', $p['parcelID']); $su->execute(); $su->close();
            $summary['overdue']++;
        }
        if ($stage === 0) continue;
        $type = 'REMINDER_' . $stage;
        if (alreadyNotified($conn, $p['parcelID'], $type)) continue;
        $fee = parcelFee($p['size'], $p['arrivalDate']);
        $msg = $stage === 3  ? "Reminder: parcel {$p['trackingNo']} has been waiting 3 days. Fee " . rm($fee['total']) . '.'
             : ($stage === 7 ? "Your parcel {$p['trackingNo']} is now overdue. An RM3.00 fine applies — total " . rm($fee['total']) . '.'
                             : "Final notice: parcel {$p['trackingNo']} has been uncollected for 14+ days. Please collect it immediately (total " . rm($fee['total']) . ').');
        notify($conn, $p['recipientUserID'], $p['parcelID'], $type, $msg);
        if ($p['recipientUserID']) $summary['sent']++;
    }
    logAudit($conn, 'RUN_REMINDERS', null, "Checked {$summary['checked']}, sent {$summary['sent']}, marked overdue {$summary['overdue']}");
}
$due = $conn->query("SELECT parcelID, trackingNo, recipientName, recipientUserID, arrivalDate, size, status FROM parcels WHERE status<>'Collected' AND DATEDIFF(NOW(),arrivalDate)>=3 ORDER BY arrivalDate ASC")->fetch_all(MYSQLI_ASSOC);
$recentN = $conn->query("SELECT createdAt, type, userID, message FROM notifications WHERE type LIKE 'REMINDER_%' ORDER BY createdAt DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Reminder Engine'; $pageSubtitle = 'Sends in-app reminders at Day 3, Day 7 (fine applied) and Day 14. Each stage is sent once per parcel.';
$pageActions = '<form method="post" style="margin:0"><button class="btn-x primary" name="run"><i class="bi bi-megaphone"></i> Run reminders now</button></form>';
require_once __DIR__ . '/includes/layouttop.php';
?>
<?php if ($summary): ?>
  <div class="notice ok"><i class="bi bi-check-circle"></i><div>Checked <b><?= $summary['checked'] ?></b> uncollected parcels · sent <b><?= $summary['sent'] ?></b> new reminders · marked <b><?= $summary['overdue'] ?></b> as overdue.</div></div>
<?php endif; ?>
<div class="grid g-main">
  <div class="card-x">
    <div class="hd"><div><h3>Parcels in reminder window</h3><div class="sub">Uncollected for 3 days or more</div></div><span class="pill neutral plain"><?= count($due) ?> parcels</span></div>
    <div class="table-wrap"><table class="table-x">
      <thead><tr><th>Reference</th><th>Recipient</th><th>Days</th><th>Stage</th><th>Account</th><th>Status</th></tr></thead>
      <tbody>
      <?php if (!$due): ?><tr><td colspan="6"><div class="empty"><i class="bi bi-emoji-smile"></i>No parcels need reminders</div></td></tr>
      <?php else: foreach ($due as $p): $d = daysSince($p['arrivalDate']); $stage = $d >= 14 ? 14 : ($d >= 7 ? 7 : 3); ?>
        <tr><td class="mono"><?= e($p['trackingNo']) ?></td><td><?= e($p['recipientName']) ?></td>
            <td><span class="pill <?= $d > 7 ? 'bad' : 'warn' ?>"><?= $d ?> days</span></td><td>Day <?= $stage ?></td>
            <td><?= $p['recipientUserID'] ? '<span class="mono">' . e($p['recipientUserID']) . '</span>' : '<span class="muted">No account — notify manually</span>' ?></td>
            <td><?= statusPill(effectiveStatus($p)) ?></td></tr>
      <?php endforeach; endif; ?>
      </tbody></table></div>
  </div>
  <div class="card-x">
    <div class="hd"><h3>Recently sent</h3></div>
    <div class="bd" style="padding-top:6px;padding-bottom:6px">
      <?php if (!$recentN): ?><div class="empty" style="padding:24px"><i class="bi bi-bell-slash"></i>No reminders sent yet</div>
      <?php else: ?><ul class="timeline"><?php foreach ($recentN as $n): ?>
        <li><span class="dot"></span><div><b><?= e(str_replace('REMINDER_', 'Day ', $n['type'])) ?></b> → <span class="mono"><?= e($n['userID']) ?></span><small><?= e($n['message']) ?><br><?= date('j M Y, H:i', strtotime($n['createdAt'])) ?></small></div></li>
      <?php endforeach; ?></ul><?php endif; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/layoutbottom.php'; ?>