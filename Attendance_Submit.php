<?php
date_default_timezone_set('Asia/Kuala_Lumpur');

$attendanceID = "ATT" . uniqid();
$studentID = $_POST["StudentID"];
$slotID = $_POST["SlotID"];
$password = $_POST["password"];
$checkInTime = date("Y-m-d H:i:s");
$actualGeolocation = $_POST["ActualGeoLocation"]; // format: "lat,lng"

// Create a connection to the database
$link = mysqli_connect("localhost", "root", "", "mypetakom");

if (!$link) {
    die("Connection failed: " . mysqli_connect_error());
}

// 1. Get slot geolocation, StartTime, EndTime
$stmt = $link->prepare("SELECT geolocation, StartTime, EndTime FROM AttendanceSlot WHERE SlotID = ?");
$stmt->bind_param("s", $slotID);
$stmt->execute();
$stmt->bind_result($eventGeolocation, $startTime, $endTime);
if (!$stmt->fetch()) {
    // Slot does not exist
    echo "<script type='text/javascript'>alert('Attendance slot not found.'); window.location.href = 'Attendance.php';</script>";
    exit();
}
$stmt->close();

// Parse geolocations
list($eventLat, $eventLng) = explode(',', $eventGeolocation); // event geolocation from DB
list($userLat, $userLng) = explode(',', $actualGeolocation);  // actual geo from user

// Function to calculate distance in meters between two lat/lng coordinates (Haversine formula)
function haversine($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371000; // in meters
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earth_radius * $c;
}

// 2. Check if user location is within 1km (1000 meters)
$distance = haversine(floatval($eventLat), floatval($eventLng), floatval($userLat), floatval($userLng));
if ($distance > 1000) {
    echo "<script type='text/javascript'>
        alert('You are not in the event');
        window.location.href = 'Attendance_Form.php?slot_id=$slotID';
        </script>";
    exit();
}

// 3. Check if check-in time is within slot range
$startDateTime = DateTime::createFromFormat('H:i:s', $startTime);
$endDateTime = DateTime::createFromFormat('H:i:s', $endTime);
$checkInDateTime = DateTime::createFromFormat('Y-m-d H:i:s', $checkInTime);

// Adjust to today's date for time comparison if StartTime/EndTime have only time part
if ($startDateTime && $endDateTime && $checkInDateTime) {
    $today = $checkInDateTime->format('Y-m-d');
    $startDateTime->setDate($checkInDateTime->format('Y'), $checkInDateTime->format('m'), $checkInDateTime->format('d'));
    $endDateTime->setDate($checkInDateTime->format('Y'), $checkInDateTime->format('m'), $checkInDateTime->format('d'));
    if ($checkInDateTime < $startDateTime || $checkInDateTime > $endDateTime) {
        echo "<script type='text/javascript'>
            alert('Attendance can only be submitted between the allowed slot times.');
            window.location.href = 'Attendance_Form.php?slot_id=$slotID';
            </script>";
        exit();
    }
}

// 4. Prepare the SQL statement to select the hashed password
$stmt = $link->prepare("SELECT studentpassword FROM student WHERE studentID = ?");
$stmt->bind_param("s", $studentID);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    // User exists, now fetch the hashed password
    $stmt->bind_result($hashedPassword);
    $stmt->fetch();

    // Verify the password
    if (password_verify($password, $hashedPassword) || $password === $hashedPassword) {
        $stmt->close(); 

        $checkStmt = $link->prepare("SELECT * FROM attendance WHERE studentID = ? AND slotID = ?");
        $checkStmt->bind_param("ss", $studentID, $slotID);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            echo "<script type='text/javascript'> 
            alert('You have already checked in for this slot.');
            window.location.href = 'Attendance.php';
            </script>";
            $checkStmt->close();
            $link->close();
            exit();
        }   

        // Prepare the SQL statement for attendance insertion
        $stmt = $link->prepare("INSERT INTO attendance (attendanceID, studentID, slotID, checkInTime, ActualGeolocation) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $attendanceID, $studentID, $slotID, $checkInTime, $actualGeolocation);

        if ($stmt->execute()) {
            echo "<script type='text/javascript'> 
                alert('Attendance added successfully!');
                window.location.href = 'Attendance.php';
                </script>";
        } else {
            echo "Error: " . $stmt->error;
        }
    } else {
        echo "<script type='text/javascript'> 
            alert('Incorrect Password');
            window.location.href = 'Attendance_Form.php?slot_id=$slotID';
            </script>";
    }
} else {
    echo "<script type='text/javascript'> 
        alert('User Not Found');
        window.location.href = 'Attendance_Form.php?slot_id=$slotID';
        </script>";
}

$stmt->close();
$link->close();
?>