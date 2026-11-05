<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/qr.php';
requireRole('User', 'Staff');
$me = currentUserID(); $id = trim($_GET['id'] ?? '');
$st = $conn->prepare("SELECT * FROM parcels WHERE (parcelID=? OR trackingNo=?) AND recipientUserID=?"); $st->bind_param('sss', $id, $id, $me); $st->execute(); $p = $st->get_result()->fetch_assoc(); $st->close();

$pageTitle = 'Pickup Pass'; $pageSubtitle = 'Present this QR code to the staff at the Koperasi counter for pickup verification.';
$pageActions = '<a class="btn-x" href="'.BASE_URL.'user/myparcels.php"><i class="bi bi-arrow-left"></i> My parcels</a>';
require_once __DIR__ . '/../includes/layouttop.php';
if (!$p): ?>
  <div class="notice bad"><i class="bi bi-x-circle"></i><div>Parcel not found or it does not belong to your account.</div></div>
<?php else: $fee = parcelFee($p['size'], $p['arrivalDate']); $done = $p['status']==='Collected'; ?>
  <div class="card-x" style="max-width:520px;margin:0 auto;overflow:hidden">
    <div style="background:linear-gradient(135deg,var(--accent),var(--accent-2));color:#fff;padding:22px;text-align:center">
      <span class="pill plain" style="background:rgba(255,255,255,.18);color:#fff">Official pickup pass</span>
      <div class="mono" style="font-size:22px;font-weight:800;margin-top:10px"><?= e($p['trackingNo']) ?></div>
      <div class="mono" style="opacity:.8;font-size:12.5px">Ref: <?= e($p['parcelID']) ?></div>
    </div>
    <div class="qr-stage">
      <div class="qr-frame" style="<?= $done ? 'filter:grayscale(1);opacity:.5' : '' ?>"><img src="<?= e(qrSrc($p['trackingNo'])) ?>" alt="QR code"></div>
      <div style="margin-top:14px"><?= statusPill(effectiveStatus($p)) ?></div>
    </div>
    <div class="bd" style="border-top:1px solid var(--line)">
      <dl class="kv"><dt>Size</dt><dd><?= e($p['size']) ?></dd><dt>Shelf</dt><dd><?= e($p['location'] ?: '—') ?></dd>
        <dt>Arrived</dt><dd><?= date('j M Y', strtotime($p['arrivalDate'])) ?> (<?= daysSince($p['arrivalDate']) ?> days)</dd>
        <?php if (!$done): ?><dt>Fee due</dt><dd style="font-weight:800;font-size:18px"><?= rm($fee['total']) ?><?= $fee['fine'] ? ' <span class="pill bad">incl. RM3 fine</span>' : '' ?></dd><?php endif; ?></dl>
      <div class="notice <?= $done ? 'ok' : 'info' ?> mt" style="margin-bottom:0"><i class="bi bi-<?= $done ? 'check-circle' : 'info-circle' ?>"></i><div><?= $done ? 'This parcel was collected on ' . date('j M Y, H:i', strtotime($p['collectedAt'] ?? $p['collectedDate'])) . '.' : 'Fees are paid in cash at the counter. Collect within 7 days to avoid the RM 3.00 fine.' ?></div></div>
    </div>
  </div>
<?php endif; require_once __DIR__ . '/../includes/layoutbottom.php'; ?>