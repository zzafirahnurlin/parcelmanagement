<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('User', 'Staff');
$me = currentUserID(); $f = $_GET['f'] ?? 'inbox';
$pk = $conn->query("SHOW KEYS FROM notifications WHERE Key_name='PRIMARY'")->fetch_assoc()['Column_name'] ?? 'notificationID';

$st = $conn->prepare("UPDATE notifications n JOIN parcels p ON p.parcelID=n.parcelID SET n.isRead=1
                      WHERE n.userID=? AND n.isRead=0 AND p.status='Collected' AND n.message NOT LIKE '%collected%'");
$st->bind_param('s', $me); $st->execute(); $st->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['read_all']))  { $st = $conn->prepare("UPDATE notifications SET isRead=1 WHERE userID=?"); $st->bind_param('s', $me); $st->execute(); $st->close(); }
    if (isset($_POST['read_one']))  { $st = $conn->prepare("UPDATE notifications SET isRead=1 WHERE `$pk`=? AND userID=?"); $st->bind_param('ss', $_POST['read_one'], $me); $st->execute(); $st->close(); }
    if (isset($_POST['dismiss']))   { $st = $conn->prepare("DELETE FROM notifications WHERE `$pk`=? AND userID=?"); $st->bind_param('ss', $_POST['dismiss'], $me); $st->execute(); $st->close(); }
    if (isset($_POST['dismiss_parcel'])) { $st = $conn->prepare("DELETE FROM notifications WHERE parcelID=? AND userID=?"); $st->bind_param('ss', $_POST['dismiss_parcel'], $me); $st->execute(); $st->close(); }
    if (isset($_POST['dismiss_all']) && !empty($_POST['ids'])) {
        $st = $conn->prepare("DELETE FROM notifications WHERE `$pk`=? AND userID=?");
        foreach (array_filter(explode(',', $_POST['ids'])) as $id) { $st->bind_param('ss', $id, $me); $st->execute(); }
        $st->close();
    }
    if (isset($_POST['clear_hist'])) {
        $st = $conn->prepare("DELETE n FROM notifications n LEFT JOIN parcels p ON p.parcelID=n.parcelID
                              WHERE n.userID=? AND n.isRead=1 AND (p.parcelID IS NULL OR p.status='Collected')");
        $st->bind_param('s', $me); $st->execute(); $st->close();
    }
    header('Location: ' . BASE_URL . 'user/notifications.php?f=' . urlencode($f)); exit;
}

$st = $conn->prepare("SELECT n.*, n.`$pk` AS nid, p.trackingNo, p.status AS pstatus
                      FROM notifications n LEFT JOIN parcels p ON p.parcelID=n.parcelID
                      WHERE n.userID=? ORDER BY n.createdAt DESC LIMIT 300");
$st->bind_param('s', $me); $st->execute(); $all = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();

$kind = function (array $r): string {
    $t = strtoupper($r['type'] ?? ''); $m = strtolower($r['message'] ?? '');
    if (str_starts_with($t, 'REMINDER') || str_contains($m, 'reminder') || str_contains($m, 'overdue') || str_contains($m, 'fine')) return 'reminder';
    if ($t === 'COLLECTED' || str_contains($m, 'collected')) return 'cleared';
    return 'arrival';
};
$isActive = fn(array $r) => !$r['isRead'] || ($r['parcelID'] && $r['pstatus'] !== 'Collected');
$style = ['arrival'=>['bi-box-seam','info','Arrival'], 'reminder'=>['bi-bell','warn','Reminder'], 'cleared'=>['bi-check2-circle','ok','Collected']];

$unread = 0; $histN = 0; $rows = []; $allIds = [];
foreach ($all as $r) {
    if (!$r['isRead']) $unread++;
    $active = $isActive($r); if (!$active) $histN++;
    $k = $kind($r);
    $show = match ($f) { 'inbox' => $active, 'history' => !$active, 'unread' => !$r['isRead'], default => $f === $k };
    if ($show) { $rows[] = $r; $allIds[] = $r['nid']; }
}

/* Group by parcel: newest notice is the headline, earlier ones go in 'earlier' */
$groups = [];
foreach ($rows as $r) {
    $key = ($f === 'inbox' && $r['parcelID']) ? $r['parcelID'] : 'n' . $r['nid'];
    if (!isset($groups[$key])) { $r['earlier'] = []; $r['anyUnread'] = !$r['isRead']; $groups[$key] = $r; }
    else { $groups[$key]['earlier'][] = $r; if (!$r['isRead']) $groups[$key]['anyUnread'] = true; }
}
$groups = array_values($groups);

$pageTitle = 'Notifications';
$pageSubtitle = $f === 'inbox'
    ? ($groups ? count($groups) . ' parcel' . (count($groups) === 1 ? '' : 's') . ' with active notices. Earlier notices for the same parcel are grouped under the latest one.' : 'Nothing pending.')
    : 'Older notices, kept for your reference until you clear them.';
$pageActions = ($unread ? '<form method="post" style="margin:0"><button class="btn-x" name="read_all"><i class="bi bi-check2-all"></i> Mark all read</button></form>' : '')
             . ($allIds ? '<form method="post" style="margin:0"><input type="hidden" name="ids" value="' . e(implode(',', $allIds)) . '"><button class="btn-x danger" name="dismiss_all" data-confirm="Dismiss all ' . count($allIds) . ' notifications in this view?"><i class="bi bi-trash3"></i> Dismiss all shown</button></form>' : '');
require_once __DIR__ . '/../includes/layouttop.php';
?>
<style>
.ntf-earlier summary{ cursor:pointer; font-size:12.5px; color:var(--accent); font-weight:600; list-style:none; display:inline-flex; align-items:center; gap:6px; margin-top:8px }
.ntf-earlier summary::-webkit-details-marker{ display:none }
.ntf-earlier summary i{ transition:transform .15s }
.ntf-earlier[open] summary i{ transform:rotate(90deg) }
.ntf-earlier ul{ list-style:none; margin:8px 0 0; padding:0 0 0 12px; border-left:2px solid var(--line) }
.ntf-earlier li{ padding:8px 0 8px 10px; font-size:13px; color:var(--ink-2); border-bottom:1px dashed var(--line) }
.ntf-earlier li:last-child{ border:0 }
.ntf-earlier li small{ display:block; color:var(--muted); font-size:11.5px; margin-top:2px }
</style>

<div class="toolbar">
  <a class="btn-x sm <?= $f==='inbox'?'primary':'' ?>" href="?f=inbox"><i class="bi bi-inbox"></i> Inbox</a>
  <a class="btn-x sm <?= $f==='unread'?'primary':'' ?>" href="?f=unread">Unread (<?= $unread ?>)</a>
  <a class="btn-x sm <?= $f==='arrival'?'primary':'' ?>" href="?f=arrival">Arrivals</a>
  <a class="btn-x sm <?= $f==='reminder'?'primary':'' ?>" href="?f=reminder">Reminders</a>
  <a class="btn-x sm <?= $f==='cleared'?'primary':'' ?>" href="?f=cleared">Collected</a>
  <div class="spacer"></div>
  <a class="btn-x sm <?= $f==='history'?'primary':'' ?>" href="?f=history"><i class="bi bi-clock-history"></i> History (<?= $histN ?>)</a>
</div>

<div class="card-x">
  <?php if (!$groups): ?>
    <div class="empty"><i class="bi bi-<?= $f==='history' ? 'archive' : 'bell-slash' ?>"></i><?= $f==='history' ? 'History is empty.' : 'All clear — nothing needs your attention.' ?></div>
  <?php else: foreach ($groups as $n): $k = $kind($n); [$ic, $cl, $lbl] = $style[$k]; $onShelf = $n['parcelID'] && $n['pstatus'] !== 'Collected'; $hot = $n['anyUnread']; $nE = count($n['earlier']); $total = $nE + 1; ?>
    <div style="display:flex;gap:14px;align-items:flex-start;padding:16px 22px;border-bottom:1px solid var(--line);<?= $hot ? 'background:#FFFDF7;box-shadow:inset 3px 0 0 var(--accent)' : 'opacity:.8' ?>">
      <span class="kpi <?= $cl ?>" style="padding:0"><span class="ico"><i class="bi <?= $ic ?>"></i></span></span>
      <div style="flex:1;min-width:0">
        <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
          <span><span class="pill <?= $cl ?> plain"><?= $lbl ?></span><?= !$onShelf && $n['parcelID'] ? ' <span class="pill neutral plain">parcel collected</span>' : '' ?></span>
          <small class="muted"><?= date('d M Y, h:i A', strtotime($n['createdAt'])) ?></small>
        </div>
        <div style="margin-top:6px;font-weight:<?= $hot ? 600 : 500 ?>"><?= e($n['message']) ?></div>
        <?php if ($n['parcelID']): ?><small class="muted">Ref: <span class="mono"><?= e($n['parcelID']) ?></span><?= $n['trackingNo'] ? ' · <span class="mono">' . e($n['trackingNo']) . '</span>' : '' ?></small><?php endif; ?>
        <?php if ($nE): ?>
          <details class="ntf-earlier">
            <summary><i class="bi bi-chevron-right"></i> Show <?= $nE ?> earlier notice<?= $nE > 1 ? 's' : '' ?> for this parcel</summary>
            <ul>
              <?php foreach ($n['earlier'] as $o): [$oi, $oc, $ol] = $style[$kind($o)]; ?>
                <li><span class="pill <?= $oc ?> plain" style="font-size:11px"><?= $ol ?></span> <?= e($o['message']) ?><small><?= date('d M Y, h:i A', strtotime($o['createdAt'])) ?></small></li>
              <?php endforeach; ?>
            </ul>
          </details>
        <?php endif; ?>
      </div>
      <div style="display:flex;gap:6px;flex-direction:column;align-items:flex-end">
        <?php if ($onShelf && $k !== 'cleared'): ?><a class="btn-x sm primary" href="<?= BASE_URL ?>user/viewqr.php?id=<?= urlencode($n['parcelID']) ?>"><i class="bi bi-qr-code"></i> QR</a><?php endif; ?>
        <?php if (!$n['isRead']): ?><form method="post" style="margin:0"><button class="btn-x sm" name="read_one" value="<?= e($n['nid']) ?>"><i class="bi bi-check2"></i> Mark read</button></form><?php endif; ?>
        <?php if ($nE): ?>
          <form method="post" style="margin:0"><button class="btn-x sm ghost" name="dismiss_parcel" value="<?= e($n['parcelID']) ?>" data-confirm="Dismiss all <?= $total ?> notices for this parcel?"><i class="bi bi-x-lg"></i> Dismiss all <?= $total ?></button></form>
        <?php else: ?>
          <form method="post" style="margin:0"><button class="btn-x sm ghost" name="dismiss" value="<?= e($n['nid']) ?>"><i class="bi bi-x-lg"></i> Dismiss</button></form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/layoutbottom.php'; ?>