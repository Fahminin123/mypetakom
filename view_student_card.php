<?php
if (!isset($_GET['id'])) {
    die("No student specified.");
}
$id = $_GET['id'];

$conn = new mysqli("localhost", "root", "", "mypetakom");
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$stmt = $conn->prepare("SELECT StudentCard FROM memberapplication WHERE StudentID = ? ORDER BY VerifiedAt DESC, MemberApplicationID DESC LIMIT 1");
$stmt->bind_param("s", $id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 1) {
    $stmt->bind_result($studentCard);
    $stmt->fetch();
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->buffer($studentCard);
    if (!$mimeType || strpos($mimeType, 'image/') !== 0) {
        $mimeType = "image/jpeg";
    }
    header("Content-Type: $mimeType");
    echo $studentCard;
} else {
    // Output a default placeholder image if no student card found
    header('Content-Type: image/png');
    readfile('image/no-card.png'); // Put a placeholder image in your image/ folder
}
$stmt->close();
$conn->close();
?>