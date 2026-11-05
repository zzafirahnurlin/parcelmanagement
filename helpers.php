<?php
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function rm($amount): string { return 'RM ' . number_format((float)$amount, 2); }
function initials(string $name): string {
    $p = preg_split('/\s+/', trim($name));
    return strtoupper(substr($p[0] ?? 'U', 0, 1) . substr($p[1] ?? '', 0, 1));
}
function statusPill(?string $s): string {
    $s = $s ?? '—';
    $map = ['Collected'=>'ok','Paid'=>'ok','Active'=>'ok','Pending'=>'warn','Uncollected'=>'warn',
            'Registered'=>'info','Notified'=>'info','Overdue'=>'bad','Inactive'=>'bad'];
    return '<span class="pill ' . ($map[$s] ?? 'neutral') . '">' . e($s) . '</span>';
}
function daysSince($date): int { return $date ? (int)floor((time() - strtotime($date)) / 86400) : 0; }
function parcelFee(string $size, $arrivalDate): array {
    $base = (strtolower($size) === 'big') ? 3.00 : 1.00;
    $fine = daysSince($arrivalDate) > 7 ? 3.00 : 0.00;
    return ['base'=>$base, 'fine'=>$fine, 'total'=>$base + $fine];
}
function effectiveStatus(array $p): string {
    if ($p['status'] === 'Collected') return 'Collected';
    return daysSince($p['arrivalDate']) > 7 ? 'Overdue' : 'Pending';
}
function logAudit(mysqli $conn, string $action, ?string $parcelID = null, string $details = ''): void {
    $actor = $_SESSION['userID'] ?? 'SYSTEM';
    $st = $conn->prepare("INSERT INTO audit_logs (actorUserID, action, parcelID, details, createdAt) VALUES (?,?,?,?,NOW())");
    $st->bind_param('ssss', $actor, $action, $parcelID, $details); $st->execute(); $st->close();
}
function notify(mysqli $conn, ?string $userID, ?string $parcelID, string $type, string $message): void {
    if (!$userID) return;
    $st = $conn->prepare("INSERT INTO notifications (userID, parcelID, type, message, isRead, createdAt) VALUES (?,?,?,?,0,NOW())");
    $st->bind_param('ssss', $userID, $parcelID, $type, $message); $st->execute(); $st->close();
}
function alreadyNotified(mysqli $conn, string $parcelID, string $type): bool {
    $st = $conn->prepare("SELECT 1 FROM notifications WHERE parcelID=? AND type=? LIMIT 1");
    $st->bind_param('ss', $parcelID, $type); $st->execute(); $ok = (bool)$st->get_result()->fetch_row(); $st->close();
    return $ok;
}
function uniqueValue(mysqli $conn, string $column, callable $make): string {
    do {
        $v = $make();
        $st = $conn->prepare("SELECT 1 FROM parcels WHERE $column=?"); $st->bind_param('s', $v); $st->execute();
        $exists = (bool)$st->get_result()->fetch_row(); $st->close();
    } while ($exists);
    return $v;
}
function newTrackingNo(mysqli $conn): string {
    return uniqueValue($conn, 'trackingNo', fn() => 'TRK-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)));
}
function newParcelID(mysqli $conn): string {
    return uniqueValue($conn, 'parcelID', fn() => 'P' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4)));
}
function newQrToken(mysqli $conn): string {
    return uniqueValue($conn, 'qrToken', fn() => bin2hex(random_bytes(32)));
}
/* Auto member ID for "Others" (no institutional ID): M001, M002 … */
function newMemberID(mysqli $conn, string $category): string {
    $prefix = ['Others'=>'M', 'Lecturer'=>'L', 'Student'=>'U'][$category] ?? 'M';
    $st = $conn->prepare("SELECT COUNT(*) FROM users WHERE userID LIKE CONCAT(?, '%')"); $st->bind_param('s', $prefix); $st->execute();
    $n = (int)$st->get_result()->fetch_row()[0]; $st->close();
    do {
        $n++; $id = $prefix . str_pad((string)$n, 3, '0', STR_PAD_LEFT);
        $st = $conn->prepare("SELECT 1 FROM users WHERE userID=?"); $st->bind_param('s', $id); $st->execute();
        $exists = (bool)$st->get_result()->fetch_row(); $st->close();
    } while ($exists);
    return $id;
}
function flash(string $key, ?string $msg = null): ?string {
    if ($msg !== null) { $_SESSION['flash'][$key] = $msg; return null; }
    $m = $_SESSION['flash'][$key] ?? null; unset($_SESSION['flash'][$key]); return $m;
}