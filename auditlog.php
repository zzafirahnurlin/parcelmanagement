<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('Admin');
$actor = trim($_GET['actor'] ?? ''); $action = trim($_GET['action'] ?? ''); $from = $_GET['from'] ?? ''; $to = $_GET['to'] ?? ''; $q = trim($_GET['q'] ?? '');
$w = []; $types = ''; $vals = [];
if ($actor !== '')  { $w[] = "a.actorUserID=?"; $types .= 's'; $vals[] = $actor; }
if ($action !== '') { $w[] = "a.action=?"; $types .= 's'; $vals[] = $action; }
if ($from !== '')   { $w[] = "DATE(a.createdAt)>=?"; $types .= 's'; $vals[] = $from; }
if ($to !== '')     { $w[] = "DATE(a.createdAt)<=?"; $types .= 's'; $vals[] = $to; }
if ($q !== '')      { $w[] = "(a.details LIKE ? OR p.trackingNo LIKE ? OR a.parcelID LIKE ?)"; $types .= 'sss'; $vals[] = "%$q%"; $vals[] = "%$q%"; $vals[] = "%$q%"; }
$sql = "SELECT a.createdAt, a.action, a.details, a.parcelID, a.actorUserID AS staffUserID, u.name AS staffName, u.role AS staffRole, p.trackingNo
        FROM audit_logs a LEFT JOIN users u ON u.userID=a.actorUserID LEFT JOIN parcels p ON p.parcelID=a.parcelID"
     . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY a.createdAt DESC LIMIT 500";
$st = $conn->prepare($sql); if ($vals) $st->bind_param($types, ...$vals); $st->execute(); $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();
$actors  = $conn->query("SELECT DISTINCT a.actorUserID, u.name FROM audit_logs a LEFT JOIN users u ON u.userID=a.actorUserID ORDER BY u.name")->fetch_all(MYSQLI_ASSOC);
$actions = array_column($conn->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetch_all(MYSQLI_ASSOC), 'action');
$tone = ['REGISTER_PARCEL'=>'info', 'COLLECT_PARCEL'=>'ok', 'RUN_REMINDERS'=>'warn', 'RESET_PASSWORD'=>'bad', 'TOGGLE_STATUS'=>'bad', 'GENERATE_REPORT'=>'info'];

$pageTitle = 'Audit Log'; $pageSubtitle = 'Every action with timestamp and staff ID — full traceability for disputes and missing parcels.';
require_once __DIR__ . '/../includes/layouttop.php';
?>
<div class="card-x" style="margin-bottom:20px"><div class="bd" style="padding:16px 22px">
  <form method="get" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
    <div class="field" style="margin:0;min-width:180px"><label>Staff</label><select name="actor"><option value="">All</option><?php foreach ($actors as $a): ?><option value="<?= e($a['actorUserID']) ?>" <?= $actor === $a['actorUserID'] ? 'selected' : '' ?>><?= e($a['name'] ?: $a['actorUserID']) ?></option><?php endforeach; ?></select></div>
    <div class="field" style="margin:0;min-width:180px"><label>Action</label><select name="action"><option value="">All</option><?php foreach ($actions as $a): ?><option <?= $action === $a ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?></select></div>
    <div class="field" style="margin:0"><label>From</label><input type="date" name="from" value="<?= e($from) ?>"></div>
    <div class="field" style="margin:0"><label>To</label><input type="date" name="to" value="<?= e($to) ?>"></div>
    <div class="field" style="margin:0;flex:1;min-width:200px"><label>Search</label><input type="text" name="q" value="<?= e($q) ?>" placeholder="TRK reference, Parcel ID or detail text"></div>
    <button class="btn-x primary"><i class="bi bi-funnel"></i> Filter</button><a class="btn-x" href="?">Clear</a>
  </form></div></div>
<div class="card-x">
  <div class="hd"><h3>Entries</h3><span class="pill neutral plain"><?= count($rows) ?> shown</span></div>
  <div class="table-wrap"><table class="table-x">
    <thead><tr><th>Date / time</th><th>Staff</th><th>Action</th><th>Parcel</th><th>Details</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="5"><div class="empty"><i class="bi bi-journal"></i>No entries match</div></td></tr>
    <?php else: foreach ($rows as $r): ?>
      <tr>
        <td class="muted" style="white-space:nowrap"><?= date('j M Y', strtotime($r['createdAt'])) ?><br><small><?= date('H:i:s', strtotime($r['createdAt'])) ?></small></td>
        <td><div class="person"><div class="avatar"><?= initials($r['staffName'] ?: $r['staffUserID'] ?: 'S') ?></div><div><?= e($r['staffName'] ?: $r['staffUserID']) ?><small class="mono"><?= e($r['staffUserID']) ?><?= $r['staffRole'] ? ' · ' . e($r['staffRole']) : '' ?></small></div></div></td>
        <td><span class="pill <?= $tone[$r['action']] ?? 'neutral' ?> plain"><?= e(str_replace('_', ' ', $r['action'])) ?></span></td>
        <td class="mono"><?= $r['trackingNo'] ? e($r['trackingNo']) . '<br><small class="muted">' . e($r['parcelID']) . '</small>' : e($r['parcelID'] ?: '—') ?></td>
        <td class="muted"><?= e($r['details']) ?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody></table></div>
</div>
<?php require_once __DIR__ . '/../includes/layoutbottom.php'; ?>