<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('Admin');
$ok = flash('ok'); $err = '';
$CATS = ['Student','Lecturer','Cafeteria Staff','Support Staff'];
$NO_ID = ['Cafeteria Staff','Support Staff'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if ($a === 'create') {
        $id = strtoupper(trim($_POST['userID'] ?? '')); $name = trim($_POST['name'] ?? ''); $email = trim($_POST['email'] ?? ''); $phone = trim($_POST['phone'] ?? '');
        $role = in_array($_POST['role'] ?? '', ['User','Staff','Admin'], true) ? $_POST['role'] : 'User'; $pw = $_POST['password'] ?? '';
        $cat = ($role === 'User' && in_array($_POST['category'] ?? '', $CATS, true)) ? $_POST['category'] : null;
        $pos = $role !== 'User' ? (trim($_POST['staff_position'] ?? '') ?: null) : null;
        if ($id === '' && $role === 'User' && in_array($cat, $NO_ID, true)) $id = newMemberID($conn, $cat);
        if (!preg_match('/^[A-Za-z0-9\-]{3,20}$/', $id) || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pw) < 8) $err = 'Check the fields: valid ID (or leave blank for cafeteria/support staff), name, email and a password of 8+ characters are required.';
        else {
            $st = $conn->prepare("SELECT 1 FROM users WHERE userID=? OR email=?"); $st->bind_param('ss', $id, $email); $st->execute(); $dup = (bool)$st->get_result()->fetch_row(); $st->close();
            if ($dup) $err = 'That ID or email already exists.';
            else {
                $h = password_hash($pw, PASSWORD_DEFAULT);
                $st = $conn->prepare("INSERT INTO users (userID, name, email, phone, password, role, category, staff_position, status) VALUES (?,?,?,?,?,?,?,?,'Active')");
                $st->bind_param('ssssssss', $id, $name, $email, $phone, $h, $role, $cat, $pos); $st->execute(); $st->close();
                logAudit($conn, 'CREATE_ACCOUNT', null, "$id · $role" . ($cat ? " · $cat" : ''));
                flash('ok', "Account $id ($role) created." . (in_array($cat, $NO_ID, true) ? " They can sign in with their email ($email)." : ''));
                header('Location: ' . BASE_URL . 'admin/manageusers.php'); exit;
            }
        }
    }
    if ($a === 'toggle' || $a === 'reset') {
        $id = $_POST['userID'] ?? '';
        if ($id === currentUserID()) $err = 'You cannot change your own account here.';
        elseif ($a === 'toggle') {
            $st = $conn->prepare("UPDATE users SET status=IF(status='Active','Inactive','Active') WHERE userID=?"); $st->bind_param('s', $id); $st->execute(); $st->close();
            logAudit($conn, 'TOGGLE_STATUS', null, $id); flash('ok', "Status updated for $id."); header('Location: ' . BASE_URL . 'admin/manageusers.php'); exit;
        } else {
            $tmp = 'Kpmb@' . random_int(1000, 9999) . '!'; $h = password_hash($tmp, PASSWORD_DEFAULT);
            $st = $conn->prepare("UPDATE users SET password=? WHERE userID=?"); $st->bind_param('ss', $h, $id); $st->execute(); $st->close();
            logAudit($conn, 'RESET_PASSWORD', null, $id); flash('ok', "Temporary password for $id: $tmp — ask them to change it after signing in."); header('Location: ' . BASE_URL . 'admin/manageusers.php'); exit;
        }
    }
}
$roleF = in_array($_GET['role'] ?? '', ['User','Staff','Admin'], true) ? $_GET['role'] : '';
$sql = "SELECT u.userID, u.name, u.email, u.phone, u.role, u.category, u.staff_position, u.status, u.created_at,
               (SELECT COUNT(*) FROM parcels p WHERE p.recipientUserID=u.userID) AS parcels
        FROM users u" . ($roleF ? " WHERE u.role='$roleF'" : '') . " ORDER BY FIELD(u.role,'Admin','Staff','User'), u.name";
$rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
$counts = array_column($conn->query("SELECT role, COUNT(*) c FROM users GROUP BY role")->fetch_all(MYSQLI_ASSOC), 'c', 'role');

$pageTitle = 'Manage Accounts'; $pageSubtitle = 'Create staff and admin accounts, deactivate users, and issue temporary passwords.';
require_once __DIR__ . '/../includes/layouttop.php';
?>
<?php if ($ok): ?><div class="notice ok" data-keep><i class="bi bi-check-circle"></i><div><?= e($ok) ?></div></div><?php endif; ?>
<?php if ($err): ?><div class="notice bad"><i class="bi bi-x-circle"></i><div><?= e($err) ?></div></div><?php endif; ?>
<div class="grid g-main">
  <div class="card-x">
    <div class="hd"><div><h3>Accounts</h3><div class="sub"><?= (int)($counts['Admin'] ?? 0) ?> admin · <?= (int)($counts['Staff'] ?? 0) ?> staff · <?= (int)($counts['User'] ?? 0) ?> members</div></div>
      <div style="display:flex;gap:6px"><a class="btn-x sm <?= $roleF === '' ? 'primary' : '' ?>" href="?">All</a><a class="btn-x sm <?= $roleF === 'Staff' ? 'primary' : '' ?>" href="?role=Staff">Staff</a><a class="btn-x sm <?= $roleF === 'User' ? 'primary' : '' ?>" href="?role=User">Members</a></div></div>
    <div class="bd" style="padding-bottom:0"><div class="input-icon"><i class="bi bi-search"></i><input class="form-control" type="text" data-filter="#tbl" placeholder="Filter by ID, name, email, phone, category…"></div></div>
    <div class="table-wrap"><table class="table-x" id="tbl">
      <thead><tr><th>User ID</th><th>Name</th><th>Contact</th><th>Role</th><th class="num">Parcels</th><th>Status</th><th class="actions">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $u): $self = $u['userID'] === currentUserID(); ?>
        <tr>
          <td class="mono"><?= e($u['userID']) ?></td>
          <td><div class="person"><div class="avatar"><?= initials($u['name'] ?? '') ?></div><div><?= e($u['name']) ?><small><?= e($u['role'] === 'User' ? ($u['category'] ?: 'Member') : ($u['staff_position'] ?: $u['role'])) ?> · since <?= date('M Y', strtotime($u['created_at'])) ?></small></div></div></td>
          <td class="muted text-sm"><?= e($u['email']) ?><br><?= e($u['phone']) ?></td>
          <td><span class="pill <?= $u['role'] === 'Admin' ? 'info' : ($u['role'] === 'Staff' ? 'ok' : 'neutral') ?> plain"><?= e($u['role']) ?></span></td>
          <td class="num"><?= (int)$u['parcels'] ?></td><td><?= statusPill($u['status']) ?></td>
          <td class="actions"><?php if (!$self): ?>
            <form method="post" style="display:inline"><input type="hidden" name="userID" value="<?= e($u['userID']) ?>"><button class="btn-x sm" name="action" value="reset" title="Issue temporary password" data-confirm="Issue a temporary password for <?= e($u['userID']) ?>?"><i class="bi bi-key"></i></button></form>
            <form method="post" style="display:inline"><input type="hidden" name="userID" value="<?= e($u['userID']) ?>"><button class="btn-x sm <?= $u['status'] === 'Active' ? 'danger' : '' ?>" name="action" value="toggle" data-confirm="<?= $u['status'] === 'Active' ? 'Deactivate' : 'Reactivate' ?> <?= e($u['userID']) ?>?"><?= $u['status'] === 'Active' ? '<i class="bi bi-person-x"></i> Deactivate' : '<i class="bi bi-person-check"></i> Activate' ?></button></form>
          <?php else: ?><span class="muted text-sm">you</span><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
  </div>
  <div class="card-x">
    <div class="hd"><div><h3>Create account</h3><div class="sub">Members can self-register; use this for staff, admin, or members who need help.</div></div></div>
    <div class="bd"><form method="post">
      <input type="hidden" name="action" value="create">
      <div class="form-row">
        <div class="field"><label>Role <span class="req">*</span></label><select name="role" id="roleSel"><option>Staff</option><option>Admin</option><option>User</option></select></div>
        <div class="field" id="catField" style="display:none"><label>Member category</label><select name="category" id="catSel"><?php foreach ($CATS as $c): ?><option><?= $c ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="field"><label id="idLabel">User ID <span class="req">*</span></label><input type="text" name="userID" id="idInput" placeholder="e.g. BCS2403-010 or L0042"><div class="help" id="idHelp">Staff who are students keep their matric number.</div></div>
      <div class="field"><label>Full name <span class="req">*</span></label><input type="text" name="name" required></div>
      <div class="field"><label>Email <span class="req">*</span></label><input type="email" name="email" required></div>
      <div class="field"><label>Phone</label><input type="text" name="phone"></div>
      <div class="field" id="posField"><label>Position</label><input type="text" name="staff_position" placeholder="e.g. Ahli Lembaga Koperasi / Pengurus"></div>
      <div class="field"><label>Password <span class="req">*</span></label><input type="password" name="password" required minlength="8"></div>
      <button class="btn-x primary block"><i class="bi bi-person-plus"></i> Create account</button>
    </form></div>
  </div>
</div>
<?php ob_start(); ?>
<script>
(function(){
  var r=document.getElementById('roleSel'), c=document.getElementById('catSel'), cf=document.getElementById('catField'),
      p=document.getElementById('posField'), idL=document.getElementById('idLabel'), idI=document.getElementById('idInput'), idH=document.getElementById('idHelp');
  function sync(){
    var u=r.value==='User', noId=u && (c.value==='Cafeteria Staff'||c.value==='Support Staff');
    cf.style.display=u?'':'none'; p.style.display=u?'none':'';
    idL.innerHTML = noId ? 'User ID <span class="muted">(optional)</span>' : 'User ID <span class="req">*</span>';
    idI.required=!noId;
    idH.textContent = noId ? 'Leave blank — the system assigns CF001 / SS001 style IDs. They sign in with email.'
                    : (u ? 'Students use their matric number; lecturers use their staff ID (e.g. L0042).' : 'Staff who are students keep their matric number.');
  }
  r.addEventListener('change',sync); c.addEventListener('change',sync); sync();
})();
</script>
<?php $extraScripts = ob_get_clean(); require_once __DIR__ . '/../includes/layoutbottom.php'; ?>