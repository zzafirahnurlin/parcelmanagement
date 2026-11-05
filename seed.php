<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

$staff1 = 'BCS2403-010';   // existing demo staff — not modified
$staff2 = 'BDA2403-005';   // new second staff (created below if missing)

/* ---- second staff account ---- */
$hs = password_hash('Staff@1234', PASSWORD_DEFAULT);
$st = $conn->prepare("INSERT IGNORE INTO users (userID,name,email,phone,password,role,category,staff_position,status) VALUES (?,'Amir','amir@kpmb.edu.my','0135566778',?,'Staff','Student','Ahli Lembaga Koperasi','Active')");
$st->bind_param('ss',$staff2,$hs); $st->execute(); $st->close();

/* ---- members ---- */
$pw = password_hash('Member@1234', PASSWORD_DEFAULT);
$members = [
  ['BDA2403-011','Farah','farah@kpmb.edu.my','0145678901','Student'],
  ['BDB2403-023','Haziq','haziq@kpmb.edu.my','0167890123','Student'],
  ['L0042','Aina','aina@kpmb.edu.my','0123344556','Lecturer'],
  ['L0057','Rosli','rosli@kpmb.edu.my','0129988776','Lecturer'],
  ['M001','Zaleha','zaleha.ismail@gmail.com','0178899001','Others'],
  ['M002','Kamarul','kamarul.bakar@gmail.com','0112233445','Others'],
];
foreach ($members as [$id,$n,$em,$ph,$cat]) {
    $st = $conn->prepare("INSERT IGNORE INTO users (userID,name,email,phone,password,role,category,status) VALUES (?,?,?,?,?,'User',?,'Active')");
    $st->bind_param('ssssss',$id,$n,$em,$ph,$pw,$cat); $st->execute(); $st->close();
}

/* ---- parcels ---- */
$parcels = [
  ['BCS2403-042','Small',0,'A2',null,$staff1],
  ['L0042','Big',1,'C1',null,$staff2],
  ['BDB2403-023','Small',2,'A4',null,$staff1],
  ['M001','Small',5,'B3',null,$staff2],
  ['M002','Big',9,'C4',null,$staff1],
  ['BDA2403-011','Small',12,'A5',null,$staff2],
  ['L0057','Small',6,'B1',1,$staff1],
  ['BCS2403-042','Big',10,'C2',2,$staff2],
  ['BDB2403-023','Small',4,'A1',3,$staff1],
  ['M001','Small',8,'B2',0,$staff2],
];
$made = 0;
foreach ($parcels as [$uid,$size,$ago,$loc,$col,$by]) {
    $u = $conn->query("SELECT name FROM users WHERE userID='".$conn->real_escape_string($uid)."'")->fetch_row();
    if (!$u) continue;
    $name = $u[0];
    $pid = newParcelID($conn); $trk = newTrackingNo($conn); $tok = newQrToken($conn);
    $fee = $size === 'Big' ? 3.00 : 1.00;
    $arr = date('Y-m-d H:i:s', strtotime("-$ago days 10:".sprintf('%02d', rand(5,55))));
    $colAt = $col !== null ? date('Y-m-d H:i:s', strtotime("-$col days 14:".sprintf('%02d', rand(0,59)))) : null;
    $status = $col !== null ? 'Collected' : ($ago > 7 ? 'Overdue' : 'Pending');
    $colBy = $col !== null ? $by : null;

    $st = $conn->prepare("INSERT INTO parcels (parcelID,trackingNo,recipientUserID,recipientName,recipientLabel,size,status,qrToken,location,baseFee,arrivalDate,registeredBy,collectedAt,collectedDate,collectedBy)
                          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $st->bind_param('sssssssssdsssss',$pid,$trk,$uid,$name,$name,$size,$status,$tok,$loc,$fee,$arr,$by,$colAt,$colAt,$colBy);
    $st->execute(); $st->close();

    $conn->query("INSERT INTO notifications (userID,parcelID,type,message,isRead,createdAt) VALUES ('$uid','$pid','ARRIVAL','Your parcel $trk has arrived at Koperasi KPMB ($size, ".rm($fee)."). Show your QR at the counter.',".($col!==null?1:0).",'$arr')");
    $conn->query("INSERT INTO audit_logs (actorUserID,action,parcelID,details,createdAt) VALUES ('$by','REGISTER_PARCEL','$pid','$trk · $name · $size · $loc','$arr')");

    if ($col !== null) {
        $fine = ($ago - $col) > 7 ? 3.00 : 0.00; $total = $fee + $fine;
        $conn->query("INSERT INTO payments (parcelID,amount,paymentType,paymentDate,method,recordedBy,fineAmount,total,paidAt,receivedBy) VALUES ('$pid',$fee,'Collection','$colAt','Cash','$by',$fine,$total,'$colAt','$by')");
        $conn->query("INSERT INTO notifications (userID,parcelID,type,message,isRead,createdAt) VALUES ('$uid','$pid','COLLECTED','Parcel $trk collected. Paid ".rm($total).($fine?' (incl. RM3.00 late fine)':'').". Thank you.',0,'$colAt')");
        $conn->query("INSERT INTO audit_logs (actorUserID,action,parcelID,details,createdAt) VALUES ('$by','COLLECT_PARCEL','$pid','$trk · paid ".rm($total).($fine?' incl. fine':'')."','$colAt')");
    }
    $made++;
}
echo "<p>Staff <b>BDA2403-005</b> (Amir) created — password <b>Staff@1234</b>.</p>";
echo "<p>Added ".count($members)." members (password <b>Member@1234</b>) and $made parcels.</p><p><b>Now DELETE seed.php.</b></p>";