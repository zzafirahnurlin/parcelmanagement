<?php
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) { header('Location: ' . homeFor(currentRole())); exit; }
$CATS = ['Student','Lecturer','Others'];
$NO_ID = ['Others'];   // no institutional ID — system assigns one, they sign in with email

$error = ''; $v = ['userID'=>'','name'=>'','email'=>'','phone'=>'','category'=>'Student'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($v as $k => $_) $v[$k] = trim($_POST[$k] ?? '');
    $pw = $_POST['password'] ?? ''; $pw2 = $_POST['password2'] ?? '';
    $needsID = !in_array($v['category'], $NO_ID, true);
    if ($v['name'] === '' || $v['email'] === '' || $v['phone'] === '' || ($needsID && $v['userID'] === '')) $error = 'Please fill in all required fields.';
    elseif ($needsID && !preg_match('/^[A-Za-z0-9\-]{3,20}$/', $v['userID'])) $error = 'ID may contain letters, numbers and dashes only (3–20 characters).';
    elseif (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $error = 'Please enter a valid email address.';
    elseif (!in_array($v['category'], $CATS, true)) $error = 'Please choose a category.';
    elseif (strlen($pw) < 8) $error = 'Password must be at least 8 characters.';
    elseif ($pw !== $pw2) $error = 'Passwords do not match.';
    else {
        $st = $conn->prepare("SELECT 1 FROM users WHERE email=?"); $st->bind_param('s', $v['email']); $st->execute();
        if ($st->get_result()->fetch_row()) $error = 'That email is already registered.'; $st->close();
        if (!$error && $needsID) {
            $st = $conn->prepare("SELECT 1 FROM users WHERE userID=?"); $st->bind_param('s', $v['userID']); $st->execute();
            if ($st->get_result()->fetch_row()) $error = 'That ID is already registered.'; $st->close();
        }
        if (!$error) {
            $id = $needsID ? strtoupper($v['userID']) : newMemberID($conn, $v['category']);
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            $st = $conn->prepare("INSERT INTO users (userID, name, email, phone, password, role, category, status) VALUES (?,?,?,?,?,'User',?,'Active')");
            $st->bind_param('ssssss', $id, $v['name'], $v['email'], $v['phone'], $hash, $v['category']); $st->execute(); $st->close();
            $_SESSION['userID'] = $id; logAudit($conn, 'SIGNUP', null, 'New member account (' . $v['category'] . ')'); unset($_SESSION['userID']);
            flash('ok', $needsID ? 'Account created. You can sign in now.' : "Account created. Your member ID is $id — you can sign in with your email.");
            header('Location: ' . BASE_URL . 'login.php'); exit;
        }
    }
}
$hasLogo = file_exists(__DIR__ . '/assets/img/logo.png');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Create account · <?= APP_NAME ?></title>
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

  <div class="auth-box wide">
    <h1>Create your account</h1>
    <p class="lead">Member accounts only. Koperasi staff accounts are created by the administrator.</p>
    <?php if ($error): ?><div class="notice bad"><i class="bi bi-x-circle"></i><div><?= e($error) ?></div></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <div class="form-row">
        <div class="field"><label>I am a <span class="req">*</span></label>
          <select name="category" id="catSel"><?php foreach ($CATS as $c): ?><option <?= $v['category'] === $c ? 'selected' : '' ?>><?= $c ?></option><?php endforeach; ?></select>
          <div class="help">Others = cafeteria staff, support staff, or anyone without a student / staff ID.</div></div>
        <div class="field" id="idField"><label id="idLabel">Student ID <span class="req">*</span></label>
          <input type="text" name="userID" id="idInput" value="<?= e($v['userID']) ?>" placeholder="e.g. BCS2403-042"></div>
      </div>
      <div class="notice info" id="noIdNote" style="display:none"><i class="bi bi-info-circle"></i><div>No ID needed — the system will assign you a member ID. You'll sign in with your <b>email</b>.</div></div>
      <div class="field"><label>Full name <span class="req">*</span></label><input type="text" name="name" value="<?= e($v['name']) ?>" required></div>
      <div class="form-row">
        <div class="field"><label>Email <span class="req">*</span></label><input type="email" name="email" value="<?= e($v['email']) ?>" required></div>
        <div class="field"><label>Phone <span class="req">*</span></label><input type="text" name="phone" value="<?= e($v['phone']) ?>" required placeholder="01X-XXXXXXX"></div>
      </div>
      <div class="form-row">
        <div class="field"><label>Password <span class="req">*</span></label><input type="password" name="password" required minlength="8" autocomplete="new-password"></div>
        <div class="field"><label>Confirm password <span class="req">*</span></label><input type="password" name="password2" required minlength="8" autocomplete="new-password"></div>
      </div>
      <button class="btn-x primary lg block" type="submit">Create account <i class="bi bi-arrow-right"></i></button>
    </form>
    <p class="alt">Already have an account? <a href="<?= BASE_URL ?>login.php">Sign in</a></p>
  </div>

  <div class="foot">Koperasi Kolej Profesional MARA Beranang · <?= date('Y') ?></div>
</div>
<script>
(function(){
  var sel=document.getElementById('catSel'), f=document.getElementById('idField'), inp=document.getElementById('idInput'), lbl=document.getElementById('idLabel'), note=document.getElementById('noIdNote');
  function sync(){
    var c=sel.value, noId=(c==='Others');
    f.style.display=noId?'none':''; note.style.display=noId?'flex':'none'; inp.required=!noId;
    if(c==='Student'){ lbl.innerHTML='Student ID <span class="req">*</span>'; inp.placeholder='e.g. BCS2403-042'; }
    if(c==='Lecturer'){ lbl.innerHTML='Staff ID <span class="req">*</span>'; inp.placeholder='e.g. L0042'; }
  }
  sel.addEventListener('change',sync); sync();
})();
</script>
</body>
</html>