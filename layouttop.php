<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('BASE_URL')) require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers.php';

$S        = $_SESSION;
$role     = $S['role']   ?? 'User';
$userName = $S['name']   ?? 'User';
$userID   = $S['userID'] ?? '';

$pageTitle    = $pageTitle    ?? 'Dashboard';
$pageSubtitle = $pageSubtitle ?? '';
$pageActions  = $pageActions  ?? '';
$current      = basename($_SERVER['PHP_SELF']);
$B            = BASE_URL;
$hasLogo      = file_exists(__DIR__ . '/../assets/img/logo.png');

$roleLabel = ['User'=>'Member Portal', 'Staff'=>'Counter Operations', 'Admin'=>'Administration'][$role] ?? 'Portal';

$unread = 0;
if (in_array($role, ['User','Staff'], true) && isset($conn) && $userID !== '') {
    try {
        $st = @$conn->prepare("SELECT COUNT(*) FROM notifications WHERE userID = ? AND isRead = 0");
        if ($st) { $st->bind_param('s', $userID); $st->execute(); $st->bind_result($unread); $st->fetch(); $st->close(); }
    } catch (Throwable $t) { $unread = 0; }
}

$navs = [
  'User' => [
    'Overview' => [
      ['Dashboard',     $B.'dashboard.php',          'bi-grid-1x2'],
      ['My Parcels',    $B.'user/myparcels.php',     'bi-box-seam'],
      ['Notifications', $B.'user/notifications.php', 'bi-bell', $unread],
    ],
    'Account' => [
      ['Profile & Password', $B.'profile.php', 'bi-person'],
    ],
  ],
  'Staff' => [
    'Counter' => [
      ['Dashboard',       $B.'dashboard.php',                'bi-grid-1x2'],
      ['Register Parcel', $B.'staff/registerparcel.php',     'bi-plus-square'],
      ['Collect & Pay',   $B.'staff/collectparcel.php',      'bi-qr-code-scan'],
      ['Uncollected',     $B.'staff/uncollectedparcels.php', 'bi-archive'],
    ],
    'Tools' => [
      ['Reminder Engine', $B.'reminder.php', 'bi-megaphone'],
    ],
    'My Account' => [
      ['My Parcels',         $B.'user/myparcels.php',     'bi-box-seam'],
      ['Notifications',      $B.'user/notifications.php', 'bi-bell', $unread],
      ['Profile & Password', $B.'profile.php',            'bi-person'],
    ],
  ],
  'Admin' => [
    'Management' => [
      ['Dashboard',       $B.'dashboard.php',         'bi-speedometer2'],
      ['Manage Accounts', $B.'admin/manageusers.php', 'bi-people'],
      ['Reports',         $B.'admin/reports.php',     'bi-file-earmark-bar-graph'],
      ['Audit Log',       $B.'admin/auditlog.php',    'bi-shield-check'],
    ],
    'Tools' => [
      ['Reminder Engine', $B.'reminder.php', 'bi-megaphone'],
    ],
    'Account' => [
      ['Profile & Password', $B.'profile.php', 'bi-person'],
    ],
  ],
];
$nav = $navs[$role] ?? $navs['User'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · <?= APP_NAME ?></title>
<?php if ($hasLogo): ?><link rel="icon" type="image/png" href="<?= $B ?>assets/img/logo.png"><?php endif; ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= $B ?>assets/css/app.css?v=5" rel="stylesheet">
</head>
<body data-role="<?= e($role) ?>">
<div class="shell">

  <aside class="side" id="side">
    <div class="brand">
      <?php if ($hasLogo): ?>
        <img src="<?= $B ?>assets/img/logo.png" alt="KPMB" style="height:40px;width:40px;object-fit:contain;background:#fff;border-radius:10px;padding:4px;flex:0 0 40px">
      <?php else: ?>
        <div class="brand-mark"><i class="bi bi-box-seam"></i></div>
      <?php endif; ?>
      <div>
        <div class="brand-name"><?= APP_NAME ?></div>
        <div class="brand-sub"><?= $roleLabel ?></div>
      </div>
    </div>

    <nav class="sidenav">
      <?php foreach ($nav as $section => $items): ?>
        <div class="label"><?= $section ?></div>
        <?php foreach ($items as $it): [$label, $url, $icon] = $it; $count = $it[3] ?? 0; ?>
          <a href="<?= $url ?>" class="<?= basename($url) === $current ? 'active' : '' ?>">
            <i class="bi <?= $icon ?>"></i><span><?= $label ?></span>
            <?php if ($count > 0): ?><span class="count"><?= (int)$count ?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>

    <div class="side-user">
      <div class="avatar"><?= initials($userName) ?></div>
      <div>
        <div class="n"><?= e($userName) ?></div>
        <div class="r mono"><?= e($userID) ?> · <?= e($role) ?></div>
      </div>
      <a class="out" href="<?= $B ?>logout.php" title="Log out"><i class="bi bi-box-arrow-right"></i></a>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <div style="display:flex;align-items:center">
        <button class="burger" id="burger" aria-label="Menu"><i class="bi bi-list"></i></button>
        <div class="crumbs"><span><?= $roleLabel ?></span><i class="bi bi-chevron-right"></i><b><?= e($pageTitle) ?></b></div>
      </div>
      <div class="top-right">
        <span class="date-chip"><i class="bi bi-calendar3"></i><?= date('D, j M Y') ?></span>
        <div class="user-chip" id="userChip">
          <div class="avatar"><?= initials($userName) ?></div>
          <div><div class="n"><?= e($userName) ?></div><div class="r"><?= e($role) ?></div></div>
          <i class="bi bi-chevron-down" style="font-size:12px;color:var(--muted)"></i>
          <div class="menu" id="userMenu">
            <a href="<?= $B ?>profile.php"><i class="bi bi-person"></i> Edit profile &amp; password</a>
            <hr>
            <a href="<?= $B ?>logout.php" class="danger"><i class="bi bi-box-arrow-right"></i> Log out</a>
          </div>
        </div>
      </div>
    </header>

    <main class="content">
      <div class="page-head">
        <div>
          <div class="eyebrow"><?= $roleLabel ?></div>
          <h1><?= e($pageTitle) ?></h1>
          <?php if ($pageSubtitle): ?><p><?= e($pageSubtitle) ?></p><?php endif; ?>
        </div>
        <?php if ($pageActions): ?><div class="page-actions"><?= $pageActions ?></div><?php endif; ?>
      </div>