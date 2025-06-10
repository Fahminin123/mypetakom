<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "mypetakom");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Only accept POST requests with claim_id
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['claim_id'])) {
    http_response_code(400);
    echo "Invalid request.";
    exit();
}

$claim_id = $_POST['claim_id'];

// Fetch the claim and document
$sql = "SELECT ProofDocument FROM meritclaim WHERE ClaimID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $claim_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    echo "Document not found.";
    exit();
}

$row = $result->fetch_assoc();
$document = $row['ProofDocument'];

if (empty($document)) {
    echo "No proof document uploaded.";
    exit();
}

// Attempt to detect file type (we assume PDF, PNG, JPG, JPEG, GIF, or fallback to octet-stream)
$finfo = new finfo(FILEINFO_MIME_TYPE);
$tmpfname = tempnam(sys_get_temp_dir(), 'doc');
file_put_contents($tmpfname, $document);
$mime = $finfo->file($tmpfname);
unlink($tmpfname);

// Set headers and output file
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="ProofDocument_' . $claim_id . '"');
header('Content-Transfer-Encoding: binary');
header('Content-Length: ' . strlen($document));
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Expires: 0');

echo $document;
exit();
?>