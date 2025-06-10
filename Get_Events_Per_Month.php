<?php
$conn = new mysqli("localhost", "root", "", "mypetakom");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


// Get current year
$currentYear = date('Y');

// Query: count events grouped by month for current year
$sql = "SELECT MONTH(EventDateandTime) AS month, COUNT(*) AS event_count
        FROM event
        WHERE YEAR(EventDateandTime) = ?
        GROUP BY MONTH(EventDateandTime)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $currentYear);
$stmt->execute();
$result = $stmt->get_result();

// Initialize with 0 for each month
$monthlyEvents = array_fill(1, 12, 0);

while ($row = $result->fetch_assoc()) {
    $month = (int)$row['month'];
    $monthlyEvents[$month] = (int)$row['event_count'];
}

$conn->close();

// Output as JSON
echo json_encode(array_values($monthlyEvents));
?>
