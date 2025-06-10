<?php
if (!isset($_GET['id'])) {
    die("No student specified.");
}
$id = $_GET['id'];

$conn = new mysqli("localhost", "root", "", "mypetakom");
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$stmt = $conn->prepare("SELECT StudentPic FROM student WHERE StudentID = ? LIMIT 1");
$stmt->bind_param("s", $id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 1) {
    $stmt->bind_result($studentPic);
    $stmt->fetch();
    if (!empty($studentPic)) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($studentPic);
        if (!$mimeType || strpos($mimeType, 'image/') !== 0) {
            $mimeType = "image/jpeg";
        }
        header("Content-Type: $mimeType");
        echo $studentPic;
        exit;
    }
}
// If there is no image, you can serve a placeholder
header('Content-Type: image/png');
readfile('image/no-profile.png');
?>