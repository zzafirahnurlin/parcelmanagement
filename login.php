<?php
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) { header('Location: ' . homeFor(currentRole())); exit; }

$error = ''; $ok = flash('ok');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = trim($_POST['userID'] ?? ''); $pw = $_POST['password'] ?? '';
    $st = $conn->prepare("SELECT userID, name, role, status, password FROM users WHERE userID=? OR email=? LIMIT 1");
    $st->bind_param('ss', $id, $id); $st->execute(); $u = $st->get_result()->fetch_assoc(); $st->close();
    if (!$u || !password_verify($pw, $u['password'] ?? '')) $error = 'Incorrect ID or password.';
    elseif (($u['status'] ?? 'Active') !== 'Active') $error = 'This account is inactive. Please contact the koperasi.';
    else {
        session_regenerate_id(true);
        $_SESSION['userID'] = $u['userID']; $_SESSION['name'] = $u['name']; $_SESSION['role'] = $u['role'];
        logAudit($conn, 'LOGIN', null, 'Signed in as ' . $u['role']);
        header('Location: ' . homeFor($u['role'])); exit;
    }
}
$hasLogo = file_exists(__DIR__ . '/assets/img/logo.png');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · <?= APP_NAME ?></title>
<?php if ($hasLogo): ?><link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/img/logo.png"><?php endif; ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>assets/css/app.css?v=5" rel="stylesheet">
</head>
<body>
<div class="auth-c">
  <div class="brand">
    <?php if ($hasLogo): ?>
      <img src="<?= BASE_URL ?>assets/img/logo.png" alt="KPM Beranang" style="height:72px;width:auto;margin-bottom:4px">
    <?php else: ?>
      <div class="brand-mark"><i class="bi bi-box-seam"></i></div>
    <?php endif; ?>
    <div><div class="brand-name"><?= APP_NAME ?></div><div class="brand-sub">Parcel Management System</div></div>
  </div>

  <div class="auth-box">
    <h1>Sign in</h1>
    <p class="lead">Use your ID or email to access your parcel account.</p>
    <?php if ($ok): ?><div class="notice ok"><i class="bi bi-check-circle"></i><div><?= e($ok) ?></div></div><?php endif; ?>
    <?php if ($error): ?><div class="notice bad"><i class="bi bi-x-circle"></i><div><?= e($error) ?></div></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <div class="field"><label>User ID or email</label>
        <div class="input-icon"><i class="bi bi-person"></i><input type="text" name="userID" required autofocus placeholder="e.g. BCS2403-042" autocomplete="off" value=""></div></div>
      <div class="field"><label>Password</label>
        <div class="input-icon"><i class="bi bi-lock"></i><input type="password" name="password" required placeholder="Enter your password" autocomplete="new-password" value=""></div></div>
      <button class="btn-x primary lg block" type="submit">Sign in <i class="bi bi-arrow-right"></i></button>
    </form>
    <p class="alt">New member? <a href="<?= BASE_URL ?>signup.php">Create an account</a></p>
  </div>

  <div class="foot">Koperasi Kolej Profesional MARA Beranang · <?= date('Y') ?></div>
</div>
</body>
</html>