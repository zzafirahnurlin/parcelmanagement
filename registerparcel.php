<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/qr.php';
requireRole('Staff', 'Admin');

$q = trim($_GET['q'] ?? ''); $matches = []; $picked = null; $error = ''; $done = null;

if ($q !== '') {
    $like = "%$q%";
    $st = $conn->prepare("SELECT userID, name, email, phone FROM users WHERE role IN ('User','Staff') AND status='Active' AND (name LIKE ? OR userID LIKE ? OR phone LIKE ? OR email LIKE ?) ORDER BY name LIMIT 15");
    $st->bind_param('ssss', $like, $like, $like, $like); $st->execute(); $matches = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();
}
if (!empty($_GET['pick'])) {
    $st = $conn->prepare("SELECT userID, name, phone FROM users WHERE userID=?"); $st->bind_param('s', $_GET['pick']); $st->execute(); $picked = $st->get_result()->fetch_assoc(); $st->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $rid  = trim($_POST['recipientUserID'] ?? '') ?: null;
    $name = trim($_POST['recipientName'] ?? '');
    $size = ($_POST['size'] ?? 'Small') === 'Big' ? 'Big' : 'Small';
    $loc  = trim($_POST['location'] ?? ''); $notes = trim($_POST['notes'] ?? '');
    if ($name === '') $error = 'Recipient name is required.';
    else {
        $pid = newParcelID($conn); $trk = newTrackingNo($conn); $tok = newQrToken($conn);
        $fee = $size === 'Big' ? 3.00 : 1.00; $by = currentUserID();
        $st = $conn->prepare("INSERT INTO parcels (parcelID, trackingNo, recipientUserID, recipientName, recipientLabel, size, status, qrToken, location, baseFee, notes, arrivalDate, registeredBy)
                              VALUES (?,?,?,?,?,?,'Pending',?,?,?,?,NOW(),?)");
        $st->bind_param('ssssssssdss', $pid, $trk, $rid, $name, $name, $size, $tok, $loc, $fee, $notes, $by); $st->execute(); $st->close();
        notify($conn, $rid, $pid, 'ARRIVAL', "Your parcel $trk has arrived at Koperasi KPMB ($size, " . rm($fee) . "). Show your QR at the counter.");
        logAudit($conn, 'REGISTER_PARCEL', $pid, "$trk · $name · $size" . ($loc ? " · $loc" : ''));
        $done = ['parcelID'=>$pid, 'trackingNo'=>$trk, 'name'=>$name, 'size'=>$size, 'location'=>$loc, 'hasAccount'=>(bool)$rid];
    }
}

$pageTitle = 'Register Parcel'; $pageSubtitle = 'Find the recipient, enter the parcel size, and the system issues the TRK reference, Parcel ID and QR code.';
require_once __DIR__ . '/../includes/layouttop.php';
?>
<?php if ($done): ?>
  <div class="grid g-main">
    <div class="card-x">
      <div class="hd"><div><h3>Parcel registered</h3><div class="sub">Write the Parcel ID on the parcel and shelve it.</div></div><?= statusPill('Pending') ?></div>
      <div class="bd">
        <div class="notice ok"><i class="bi bi-check-circle"></i><div>Saved. <?= $done['hasAccount'] ? 'The member has been notified in the portal.' : 'No linked account — please inform the recipient manually.' ?></div></div>
        <dl class="kv" style="grid-template-columns:160px 1fr">
          <dt>Parcel ID</dt><dd class="mono" style="font-size:24px;font-weight:800"><?= e($done['parcelID']) ?></dd>
          <dt>TRK reference</dt><dd class="mono"><?= e($done['trackingNo']) ?></dd>
          <dt>Recipient</dt><dd><?= e($done['name']) ?></dd>
          <dt>Size / fee</dt><dd><?= e($done['size']) ?> · <?= $done['size'] === 'Big' ? 'RM 3.00' : 'RM 1.00' ?></dd>
          <dt>Location</dt><dd><?= e($done['location'] ?: '—') ?></dd>
        </dl>
        <div style="display:flex;gap:10px;margin-top:22px">
          <a class="btn-x primary" href="<?= BASE_URL ?>staff/registerparcel.php"><i class="bi bi-plus-lg"></i> Register another</a>
          <a class="btn-x" href="<?= BASE_URL ?>staff/collectparcel.php?q=<?= e($done['trackingNo']) ?>"><i class="bi bi-qr-code-scan"></i> Go to collection</a>
          <a class="btn-x" href="javascript:window.print()"><i class="bi bi-printer"></i> Print label</a>
        </div>
      </div>
    </div>
    <div class="card-x"><div class="qr-stage">
      <div class="qr-frame"><img src="<?= e(qrSrc($done['trackingNo'])) ?>" alt="QR"></div>
      <div class="qr-ref mono"><?= e($done['trackingNo']) ?></div>
      <div class="muted text-sm mono"><?= e($done['parcelID']) ?></div>
    </div></div>
  </div>
<?php else: ?>
  <?php if ($error): ?><div class="notice bad"><i class="bi bi-x-circle"></i><div><?= e($error) ?></div></div><?php endif; ?>
  <div class="grid g-main">
    <div style="display:flex;flex-direction:column;gap:20px">
      <div class="card-x">
        <div class="hd"><div><h3>1 · Find the recipient</h3><div class="sub">Works with partial names, IDs, phone numbers or emails — even if the parcel label shows a nickname.</div></div></div>
        <div class="bd">
          <form method="get" style="display:flex;gap:10px">
            <div class="input-icon" style="flex:1"><i class="bi bi-search"></i><input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="e.g. Ahmad, BCS2403, 0123…" autofocus></div>
            <button class="btn-x primary">Search</button>
          </form>
          <?php if ($q !== ''): ?>
            <div class="table-wrap" style="margin-top:16px"><table class="table-x">
              <thead><tr><th>Member</th><th>Phone</th><th>Email</th><th class="actions"></th></tr></thead>
              <tbody>
              <?php if (!$matches): ?><tr><td colspan="4"><div class="empty" style="padding:22px"><i class="bi bi-person-x"></i>No member matched "<?= e($q) ?>". You can still register by typing the name below.</div></td></tr>
              <?php else: foreach ($matches as $m): ?>
                <tr><td><div class="person"><div class="avatar"><?= initials($m['name']) ?></div><div><?= e($m['name']) ?><small class="mono"><?= e($m['userID']) ?></small></div></div></td>
                    <td><?= e($m['phone']) ?></td><td class="muted"><?= e($m['email']) ?></td>
                    <td class="actions"><a class="btn-x sm primary" href="?q=<?= urlencode($q) ?>&pick=<?= urlencode($m['userID']) ?>">Select</a></td></tr>
              <?php endforeach; endif; ?>
              </tbody></table></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card-x">
        <div class="hd"><div><h3>2 · Parcel details</h3><div class="sub"><?= $picked ? 'Linked to member ' . e($picked['userID']) : 'No member selected — the parcel will be registered by name only.' ?></div></div></div>
        <div class="bd"><form method="post">
          <input type="hidden" name="recipientUserID" value="<?= e($picked['userID'] ?? '') ?>">
          <div class="form-row">
            <div class="field"><label>Recipient name <span class="req">*</span></label><input type="text" name="recipientName" value="<?= e($picked['name'] ?? '') ?>" required <?= $picked ? 'readonly' : '' ?>></div>
            <div class="field"><label>Phone</label><input type="text" value="<?= e($picked['phone'] ?? '') ?>" disabled placeholder="—"></div>
          </div>
          <div class="form-row">
            <div class="field"><label>Parcel size <span class="req">*</span></label>
              <select name="size" required><option value="Small">Small — RM 1.00</option><option value="Big">Big — RM 3.00</option></select></div>
            <div class="field"><label>Shelf / box location</label><input type="text" name="location" placeholder="e.g. A3"></div>
          </div>
          <div class="field"><label>Notes</label><input type="text" name="notes" placeholder="Optional — fragile, damaged box, courier name…"></div>
          <div style="display:flex;justify-content:flex-end;gap:10px">
            <a class="btn-x" href="<?= BASE_URL ?>dashboard.php">Cancel</a>
            <button class="btn-x primary lg" name="register"><i class="bi bi-qr-code"></i> Register &amp; generate QR</button>
          </div>
        </form></div>
      </div>
    </div>

    <div class="card-x"><div class="hd"><h3>What happens next</h3></div>
      <div class="bd"><ul class="timeline">
        <li><span class="dot"></span><div>Reference <b class="mono">TRK-<?= date('Y') ?>-…</b> and Parcel ID created<small>Write the Parcel ID on the parcel</small></div></li>
        <li><span class="dot"></span><div>QR code generated<small>The member sees it in their portal</small></div></li>
        <li><span class="dot"></span><div>Member notified instantly<small>Reminders on Day 3, 7 and 14</small></div></li>
        <li><span class="dot"></span><div>Audit log entry recorded<small>Your staff ID and timestamp</small></div></li>
      </ul></div></div>
  </div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/layoutbottom.php'; ?>