<?php
if (!isset($_GET['id'])) {
    die("No staff specified.");
}
$id = $_GET['id'];

$conn = new mysqli("localhost", "root", "", "mypetakom");
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$stmt = $conn->prepare("SELECT StaffPic FROM staff WHERE StaffID = ? LIMIT 1");
$stmt->bind_param("s", $id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 1) {
    $stmt->bind_result($staffPic);
    $stmt->fetch();
    if (!empty($staffPic)) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($staffPic);
        if (!$mimeType || strpos($mimeType, 'image/') !== 0) {
            $mimeType = "image/jpeg";
        }
        header("Content-Type: $mimeType");
        echo $staffPic;
        exit;
    }
}
// If there is no image, you can serve a placeholder
header('Content-Type: image/png');
readfile('image/no-profile.png');
?>