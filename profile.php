<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$me = currentUserID(); $ok = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $name = trim($_POST['name'] ?? ''); $email = trim($_POST['email'] ?? ''); $phone = trim($_POST['phone'] ?? '');
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Please enter a valid name and email.';
    else {
        $st = $conn->prepare("UPDATE users SET name=?, email=?, phone=? WHERE userID=?"); $st->bind_param('ssss', $name, $email, $phone, $me); $st->execute(); $st->close();
        $_SESSION['name'] = $name; logAudit($conn, 'UPDATE_PROFILE', null, 'Updated contact details'); $ok = 'Personal details saved.';
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_password'])) {
    $cur = $_POST['current'] ?? ''; $new = $_POST['new'] ?? ''; $new2 = $_POST['new2'] ?? '';
    $st = $conn->prepare("SELECT password FROM users WHERE userID=?"); $st->bind_param('s', $me); $st->execute(); $hash = $st->get_result()->fetch_row()[0] ?? ''; $st->close();
    if (!password_verify($cur, $hash)) $err = 'Current password is incorrect.';
    elseif (strlen($new) < 8) $err = 'New password must be at least 8 characters.';
    elseif ($new !== $new2) $err = 'New passwords do not match.';
    else {
        $h = password_hash($new, PASSWORD_DEFAULT);
        $st = $conn->prepare("UPDATE users SET password=? WHERE userID=?"); $st->bind_param('ss', $h, $me); $st->execute(); $st->close();
        logAudit($conn, 'CHANGE_PASSWORD', null, 'Password changed'); $ok = 'Password updated.';
    }
}
$st = $conn->prepare("SELECT * FROM users WHERE userID=?"); $st->bind_param('s', $me); $st->execute(); $u = $st->get_result()->fetch_assoc(); $st->close();
$since = $u['createdAt'] ?? $u['created_at'] ?? $u['joinedAt'] ?? null;

$pageTitle = 'Profile & Password'; $pageSubtitle = 'Keep your contact details current so parcel notifications reach you.';
require_once __DIR__ . '/includes/layouttop.php';
?>
<?php if ($ok): ?><div class="notice ok"><i class="bi bi-check-circle"></i><div><?= e($ok) ?></div></div><?php endif; ?>
<?php if ($err): ?><div class="notice bad"><i class="bi bi-x-circle"></i><div><?= e($err) ?></div></div><?php endif; ?>

<div class="card-x" style="margin-bottom:20px"><div class="bd" style="display:flex;align-items:center;gap:18px">
  <div class="avatar" style="width:64px;height:64px;font-size:22px;flex:0 0 64px"><?= initials($u['name'] ?? 'U') ?></div>
  <div>
    <div style="font-size:20px;font-weight:800;display:flex;align-items:center;gap:10px"><?= e($u['name'] ?? $me) ?> <span class="pill info plain"><?= e($u['role'] ?? currentRole()) ?></span> <?= statusPill($u['status'] ?? 'Active') ?></div>
    <div class="muted text-sm" style="margin-top:4px">User ID <span class="mono"><?= e($me) ?></span>
      <?= !empty($u['position']) ? ' · Position <b>' . e($u['position']) . '</b>' : '' ?>
      <?= $since ? ' · Member since <b>' . date('j M Y', strtotime($since)) . '</b>' : '' ?></div>
  </div>
</div></div>

<div class="grid g2">
  <div class="card-x">
    <div class="hd"><div><h3><i class="bi bi-person-lines-fill"></i> Personal information</h3><div class="sub">Used for parcel notifications and recipient matching.</div></div></div>
    <div class="bd"><form method="post">
      <div class="field"><label>User ID (permanent)</label><input type="text" value="<?= e($me) ?>" disabled><div class="help">Your institutional ID cannot be changed.</div></div>
      <div class="field"><label>Full name <span class="req">*</span></label><input type="text" name="name" value="<?= e($u['name'] ?? '') ?>" required></div>
      <div class="field"><label>Email address <span class="req">*</span></label><input type="email" name="email" value="<?= e($u['email'] ?? '') ?>" required></div>
      <div class="field"><label>Phone number</label><input type="text" name="phone" value="<?= e($u['phone'] ?? '') ?>" placeholder="e.g. 012-3456789"></div>
      <button class="btn-x primary block" name="save_profile"><i class="bi bi-check-lg"></i> Save personal details</button>
    </form></div>
  </div>
  <div class="card-x">
    <div class="hd"><div><h3><i class="bi bi-shield-lock"></i> Security &amp; password</h3><div class="sub">Use at least 8 characters to protect your collection passes.</div></div></div>
    <div class="bd"><form method="post">
      <div class="field"><label>Current password</label><input type="password" name="current" required placeholder="Enter current password"></div>
      <div class="field"><label>New password</label><input type="password" name="new" required minlength="8" placeholder="Minimum 8 characters"></div>
      <div class="field"><label>Confirm new password</label><input type="password" name="new2" required minlength="8" placeholder="Re-type new password"></div>
      <button class="btn-x primary block" name="save_password"><i class="bi bi-key"></i> Update password</button>
    </form></div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/layoutbottom.php'; ?>