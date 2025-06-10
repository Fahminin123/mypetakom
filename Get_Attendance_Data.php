<?php
$conn = new mysqli("localhost", "root", "", "mypetakom");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT Event.EventTitle, COUNT(Attendance.AttendanceID) AS AttendanceCount
    FROM Attendance 
    JOIN AttendanceSlot ON Attendance.SlotID = AttendanceSlot.SlotID
    JOIN event ON AttendanceSlot.EventID = Event.EventID
    GROUP BY Event.EventID, Event.EventTitle
    ORDER BY Event.EventTitle ASC
";

$result = $conn->query($sql);

$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

header('Content-Type: application/json');
echo json_encode($data);

$conn->close();
?>
