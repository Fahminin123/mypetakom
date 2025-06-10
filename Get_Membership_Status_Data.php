<?php
// Returns: { "approved": number, "rejected": number }
$conn = new mysqli("localhost", "root", "", "mypetakom");
header('Content-Type: application/json');
$approved = 0; $rejected = 0;
$res = $conn->query("SELECT MemStatus, COUNT(*) as cnt FROM memberapplication GROUP BY MemStatus");
while ($row = $res->fetch_assoc()) {
    if (strtolower($row['MemStatus']) == "approved") $approved = (int)$row['cnt'];
    if (strtolower($row['MemStatus']) == "rejected") $rejected = (int)$row['cnt'];
}
echo json_encode(['approved' => $approved, 'rejected' => $rejected]);