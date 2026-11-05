<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('Staff', 'Admin');

$q = trim($_GET['q'] ?? ''); $parcel = null; $error = ''; $receipt = null;

function findParcel(mysqli $conn, string $q): ?array {
    $q = ltrim($q, '#');
    $st = $conn->prepare("SELECT p.*, u.phone AS recipientPhone FROM parcels p LEFT JOIN users u ON u.userID=p.recipientUserID WHERE p.trackingNo=? OR p.parcelID=? OR p.qrToken=? LIMIT 1");
    $st->bind_param('sss', $q, $q, $q); $st->execute(); $r = $st->get_result()->fetch_assoc(); $st->close();
    return $r ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collect'])) {
    $pid = trim($_POST['parcelID'] ?? '');
    $p = findParcel($conn, $pid);
    if (!$p) $error = 'Parcel not found.';
    elseif ($p['status'] === 'Collected') $error = 'This parcel was already collected on ' . date('j M Y, H:i', strtotime($p['collectedAt'] ?? $p['collectedDate'])) . '.';
    else {
        $fee = parcelFee($p['size'], $p['arrivalDate']); $by = currentUserID();
        $conn->begin_transaction();
        $st = $conn->prepare("INSERT INTO payments (parcelID, amount, paymentType, paymentDate, method, recordedBy, fineAmount, total, paidAt, receivedBy) VALUES (?,?, 'Collection', NOW(),'Cash',?,?,?,NOW(),?)");
        $st->bind_param('sdsdds', $pid, $fee['base'], $by, $fee['fine'], $fee['total'], $by); $st->execute(); $st->close();
        $st = $conn->prepare("UPDATE parcels SET status='Collected', collectedAt=NOW(), collectedDate=NOW(), collectedBy=? WHERE parcelID=?"); $st->bind_param('ss', $by, $pid); $st->execute(); $st->close();
        $conn->commit();
        notify($conn, $p['recipientUserID'], $pid, 'COLLECTED', "Parcel {$p['trackingNo']} collected. Paid " . rm($fee['total']) . ($fee['fine'] ? ' (incl. RM3.00 late fine)' : '') . '. Thank you.');
        logAudit($conn, 'COLLECT_PARCEL', $pid, "{$p['trackingNo']} · paid " . rm($fee['total']) . ($fee['fine'] ? ' incl. fine' : ''));
        $receipt = ['p'=>$p, 'fee'=>$fee, 'time'=>date('j M Y, H:i')];
    }
}
if ($q !== '' && !$receipt) { $parcel = findParcel($conn, $q); if (!$parcel) $error = "No parcel matches \"$q\"."; }

$today = $conn->query("SELECT p.trackingNo, p.recipientName, p.collectedAt, (SELECT SUM(COALESCE(NULLIF(total,0),amount)) FROM payments py WHERE py.parcelID=p.parcelID) AS total FROM parcels p WHERE p.status='Collected' AND DATE(p.collectedAt)=CURDATE() ORDER BY p.collectedAt DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);
$todayTotal = (float)$conn->query("SELECT COALESCE(SUM(COALESCE(NULLIF(total,0),amount)),0) FROM payments WHERE DATE(paymentDate)=CURDATE()")->fetch_row()[0];

$pageTitle = 'Collect & Pay'; $pageSubtitle = 'Scan the member QR (or type the TRK reference / Parcel ID), confirm the fee, record cash and release.';
require_once __DIR__ . '/../includes/layouttop.php';
?>
<?php if ($error): ?><div class="notice bad"><i class="bi bi-x-circle"></i><div><?= e($error) ?></div></div><?php endif; ?>
<div class="grid g-main">
  <div style="display:flex;flex-direction:column;gap:20px">
    <?php if ($receipt): $p = $receipt['p']; $fee = $receipt['fee']; ?>
      <div class="card-x">
        <div class="hd"><div><h3>Collection recorded</h3><div class="sub"><?= $receipt['time'] ?> · received by <?= e($_SESSION['name'] ?? currentUserID()) ?></div></div><?= statusPill('Collected') ?></div>
        <div class="bd">
          <div class="notice ok"><i class="bi bi-check-circle"></i><div>Payment of <b><?= rm($fee['total']) ?></b> recorded and parcel released.</div></div>
          <dl class="kv"><dt>Reference</dt><dd class="mono"><?= e($p['trackingNo']) ?></dd><dt>Parcel ID</dt><dd class="mono"><?= e($p['parcelID']) ?></dd>
            <dt>Recipient</dt><dd><?= e($p['recipientName']) ?></dd><dt>Collection fee</dt><dd><?= rm($fee['base']) ?></dd>
            <dt>Late fine</dt><dd><?= rm($fee['fine']) ?></dd><dt>Total paid</dt><dd style="font-size:20px;font-weight:800"><?= rm($fee['total']) ?></dd></dl>
          <div style="display:flex;gap:10px;margin-top:20px"><a class="btn-x primary" href="<?= BASE_URL ?>staff/collectparcel.php"><i class="bi bi-qr-code-scan"></i> Next collection</a><a class="btn-x" href="javascript:window.print()"><i class="bi bi-printer"></i> Print receipt</a></div>
        </div>
      </div>
    <?php elseif ($parcel): $fee = parcelFee($parcel['size'], $parcel['arrivalDate']); $d = daysSince($parcel['arrivalDate']); $collected = $parcel['status'] === 'Collected'; ?>
      <div class="card-x">
        <div class="hd"><div><h3>Parcel found</h3><div class="sub">Confirm the member's identity before releasing.</div></div><?= statusPill(effectiveStatus($parcel)) ?></div>
        <div class="bd">
          <?php if ($collected): ?><div class="notice warn"><i class="bi bi-exclamation-triangle"></i><div>Already collected on <?= date('j M Y, H:i', strtotime($parcel['collectedAt'] ?? $parcel['collectedDate'])) ?> by <?= e($parcel['collectedBy']) ?>.</div></div><?php endif; ?>
          <?php if ($d > 7 && !$collected): ?><div class="notice bad"><i class="bi bi-clock-history"></i><div>In storage <b><?= $d ?> days</b> — RM 3.00 late fine applies.</div></div><?php endif; ?>
          <div class="grid g2">
            <dl class="kv"><dt>Reference</dt><dd class="mono"><?= e($parcel['trackingNo']) ?></dd><dt>Parcel ID</dt><dd class="mono"><?= e($parcel['parcelID']) ?></dd>
              <dt>Recipient</dt><dd><?= e($parcel['recipientName']) ?><?= $parcel['recipientUserID'] ? '<br><span class="mono muted">' . e($parcel['recipientUserID']) . '</span>' : '' ?></dd>
              <dt>Phone</dt><dd><?= e($parcel['recipientPhone'] ?: '—') ?></dd></dl>
            <dl class="kv"><dt>Size</dt><dd><?= e($parcel['size']) ?></dd><dt>Location</dt><dd><?= e($parcel['location'] ?: '—') ?></dd>
              <dt>Arrived</dt><dd><?= date('j M Y, H:i', strtotime($parcel['arrivalDate'])) ?><br><span class="muted"><?= $d ?> day<?= $d === 1 ? '' : 's' ?> ago</span></dd>
              <dt>Notes</dt><dd><?= e($parcel['notes'] ?: '—') ?></dd></dl>
          </div>
          <?php if (!$collected): ?>
            <hr class="divider">
            <div class="grid g3" style="gap:12px">
              <div class="card-x kpi" style="padding:14px 16px"><span class="lbl">Collection fee</span><div class="val" style="font-size:22px"><?= rm($fee['base']) ?></div></div>
              <div class="card-x kpi <?= $fee['fine'] ? 'bad' : '' ?>" style="padding:14px 16px"><span class="lbl">Late fine</span><div class="val" style="font-size:22px"><?= rm($fee['fine']) ?></div></div>
              <div class="card-x kpi ok" style="padding:14px 16px"><span class="lbl">Total due</span><div class="val" style="font-size:22px"><?= rm($fee['total']) ?></div></div>
            </div>
            <form method="post" style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px">
              <input type="hidden" name="parcelID" value="<?= e($parcel['parcelID']) ?>">
              <a class="btn-x" href="<?= BASE_URL ?>staff/collectparcel.php">Cancel</a>
              <button class="btn-x primary lg" name="collect" data-confirm="Record cash payment of <?= rm($fee['total']) ?> and release the parcel?"><i class="bi bi-cash-coin"></i> Record <?= rm($fee['total']) ?> &amp; release</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="card-x">
      <div class="hd"><h3>Find a parcel</h3></div>
      <div class="bd"><form method="get" style="display:flex;gap:10px">
        <div class="input-icon" style="flex:1"><i class="bi bi-upc-scan"></i><input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="Scan QR, or type TRK-2026-… or Parcel ID" <?= $parcel || $receipt ? '' : 'autofocus' ?>></div>
        <button class="btn-x primary"><i class="bi bi-search"></i> Look up</button>
      </form><div class="help muted text-sm" style="margin-top:8px">A USB/Bluetooth scanner types the code and presses Enter automatically.</div></div>
    </div>
  </div>

  <div class="card-x">
    <div class="hd"><div><h3>Collected today</h3><div class="sub">Cash received: <b><?= rm($todayTotal) ?></b></div></div></div>
    <div class="table-wrap"><table class="table-x">
      <thead><tr><th>Reference</th><th>Recipient</th><th class="num">Paid</th></tr></thead>
      <tbody>
      <?php if (!$today): ?><tr><td colspan="3"><div class="empty" style="padding:24px"><i class="bi bi-inbox"></i>No collections yet today</div></td></tr>
      <?php else: foreach ($today as $t): ?>
        <tr><td class="mono"><?= e($t['trackingNo']) ?><br><small class="muted"><?= date('H:i', strtotime($t['collectedAt'])) ?></small></td><td><?= e($t['recipientName']) ?></td><td class="num mono"><?= rm($t['total']) ?></td></tr>
      <?php endforeach; endif; ?>
      </tbody></table></div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/layoutbottom.php'; ?>