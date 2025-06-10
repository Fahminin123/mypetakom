<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['type_user'] !== 'event_advisor') {
    echo "Unauthorized.";
    exit();
}

//database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mypetakom";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

//QR library
include('phpqrcode/qrlib.php');

$event_id = $_GET['eventid'] ?? '';
if (!$event_id) { echo "Invalid event."; exit(); }

// Fetch event, ensure it belongs to this advisor
$stmt = $conn->prepare("SELECT * FROM event WHERE EventID = ? AND StaffID = ?");
$stmt->bind_param("ss", $event_id, $_SESSION['user_id']);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) { echo "Event not found."; exit(); }

$qrcode = null;
if ($event['QRCodeID']) {
    // Get QR record
    $stmt = $conn->prepare("SELECT * FROM qrcode WHERE QRCodeID = ?");
    $stmt->bind_param("s", $event['QRCodeID']);
    $stmt->execute();
    $qrcode = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Delete old image file if exists
    if ($qrcode && !empty($qrcode['Image_URL']) && file_exists($qrcode['Image_URL'])) {
        @unlink($qrcode['Image_URL']);
    }

    $desc = "Event: {$event['EventTitle']}";
    $url = "http://10.65.73.219/BCS2243/mypetakom/EventInfo.php?eventid=" . urlencode($event['EventID']);
    $qr_dir = "uploads/qrcodes/";
    if (!is_dir($qr_dir)) mkdir($qr_dir, 0777, true);
    $qr_filename = $qr_dir . $event['EventID'] . "_" . uniqid() . ".png";
    QRcode::png($url, $qr_filename, 'H', 10, 4);

    // Update the QR image path (do not change QRCodeID or create new qrcode entry)
    $stmt = $conn->prepare("UPDATE qrcode SET Image_URL = ?, QR_Desc = ? WHERE QRCodeID = ?");
    $stmt->bind_param("sss", $qr_filename, $desc, $event['QRCodeID']);
    $stmt->execute();
    $stmt->close();

    $qrcode['Image_URL'] = $qr_filename;
    $qrcode['QR_Desc'] = $desc;
} else {
    // If event has no QRCodeID, create QR record as before
    $desc = "Event: {$event['EventTitle']}";
    $url = "http://10.65.66.123/BCS2243/mypetakom/EventInfo.php?eventid=" . urlencode($event['EventID']);
    $qr_dir = "uploads/qrcodes/";
    if (!is_dir($qr_dir)) mkdir($qr_dir, 0777, true);
    $qr_id = "QR" . uniqid();
    $qr_filename = $qr_dir . $event['EventID'] . "_" . uniqid() . ".png";
    QRcode::png($url, $qr_filename, 'H', 10, 4);

    $stmt = $conn->prepare("INSERT INTO qrcode (QRCodeID, Image_URL, QR_Desc) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $qr_id, $qr_filename, $desc);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE event SET QRCodeID = ? WHERE EventID = ?");
    $stmt->bind_param("ss", $qr_id, $event['EventID']);
    $stmt->execute();
    $stmt->close();

    $qrcode = ['QRCodeID'=>$qr_id, 'Image_URL'=>$qr_filename, 'QR_Desc'=>$desc];
}
echo "<div>
        <img src='" . htmlspecialchars($qrcode['Image_URL']) . "' alt='QR Code' style='width:180px;'><br>
        <div style='margin:10px 0; font-size:1.05em;'>" . htmlspecialchars($qrcode['QR_Desc']) . "</div>
      </div>";
?>