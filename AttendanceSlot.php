<?php
require_once 'phpqrcode/qrlib.php';

// Database connection
$conn = new mysqli("localhost", "root", "", "mypetakom");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch list of events for the dropdown
$event_sql = "SELECT EventID, EventTitle FROM event";
$event_result = $conn->query($event_sql);

// Fetch all attendance slots (with QRCode join)
$search_term = isset($_GET['search_event']) ? trim($_GET['search_event']) : '';

if (!empty($search_term)) {
    $slots_sql = "SELECT AttendanceSlot.SlotID, AttendanceSlot.EventID, AttendanceSlot.StartTime, AttendanceSlot.EndTime,
                 AttendanceSlot.QRCodeID, event.EventTitle, event.EventVenue, qrcode.Image_URL 
                FROM AttendanceSlot 
                JOIN event ON AttendanceSlot.EventID = event.EventID 
                LEFT JOIN qrcode ON AttendanceSlot.QRCodeID = qrcode.QRCodeID
                WHERE event.EventTitle LIKE ?
                ORDER BY AttendanceSlot.SlotID DESC";

    $stmt = $conn->prepare($slots_sql);
    $like_search = "%" . $search_term . "%";
    $stmt->bind_param("s", $like_search);
    $stmt->execute();
    $slots_result = $stmt->get_result();
} else {
    $slots_sql = "SELECT AttendanceSlot.SlotID, AttendanceSlot.EventID, AttendanceSlot.StartTime, AttendanceSlot.EndTime,
                 AttendanceSlot.QRCodeID, event.EventTitle, event.EventVenue, qrcode.Image_URL 
                FROM AttendanceSlot 
                JOIN event ON AttendanceSlot.EventID = event.EventID 
                LEFT JOIN qrcode ON AttendanceSlot.QRCodeID = qrcode.QRCodeID
                ORDER BY AttendanceSlot.SlotID DESC";
    $slots_result = $conn->query($slots_sql);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $event_id = $_POST['event_id'];

    // Get geolocation from the selected event
    $geo_sql = "SELECT geolocation FROM event WHERE EventID = ?";
    $geo_stmt = $conn->prepare($geo_sql);
    $geo_stmt->bind_param("s", $event_id);
    $geo_stmt->execute();
    $geo_result = $geo_stmt->get_result();

    if ($geo_result->num_rows == 1) {
        $geo_row = $geo_result->fetch_assoc();
        $geolocation = $geo_row['geolocation'];

        // Generate unique SlotID
        $slot_id = uniqid('SLOT', true);
        $start_time = $_POST['StartTime'];
        $end_time = $_POST['EndTime'] ?? null;
        $qr_data = "https://10.65.73.219/BCS2243/mypetakom/Attendance_Form.php?slot_id=" . urlencode($slot_id); // Set actual slot id
        $qrcode_id = uniqid('QR_', true) . '.png';
        $qr_filename = $qrcode_id;
        $qr_filepath = "QR_Codes/$qr_filename";

        // Ensure the folder exists
        if (!file_exists('QR_Codes')) {
            mkdir('QR_Codes', 0777, true);
        }

        QRcode::png($qr_data, $qr_filepath, QR_ECLEVEL_H, 5);

        // Store filename as QRCodeID
        $qrcode_id = $qr_filename;

        // Check for duplicate slot
        $check_sql = "SELECT * FROM AttendanceSlot WHERE EventID = ? AND StartTime = ? AND EndTime = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("sss", $event_id, $start_time, $end_time);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            echo "<script>alert('An attendance slot for this event and time already exists.'); window.location.href='AttendanceSlot.php';</script>";
            exit;
        }

        // insert into QRCode
        $insert_qr_sql = "INSERT INTO qrcode (QRCodeID, QR_Desc, Image_URL) VALUES (?, ?, ?)";
        $insert_qr_stmt = $conn->prepare($insert_qr_sql);
        $insert_qr_stmt->bind_param("sss", $qrcode_id, $event_id, $qr_filepath);

        $qr_success = $insert_qr_stmt->execute();

        // insert into AttendanceSlot
        $insert_sql = "INSERT INTO AttendanceSlot (SlotID, EventID, geolocation, QRCodeID, StartTime, EndTime) VALUES (?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ssssss", $slot_id, $event_id, $geolocation, $qrcode_id, $start_time, $end_time);

        $slot_success = $insert_stmt->execute();

        // Check if both succeeded
        if ($qr_success && $slot_success) {
            echo "<script>alert('Slot successfully created!'); window.location.href='AttendanceSlot.php';</script>";
        } else {
            echo "<script>alert('Failed to create slot.');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title> Event Attendance Slot</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f5f5f5;
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .header {
            background-color: #1f2d3d;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            position: fixed;
            width: 100%;
            box-sizing: border-box;
            height: 120px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 0 35px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-right: 20px;
        }

        .Logo {
            display: flex;
            gap: 20px;
            align-items: center;
            padding: 0 60px;
        }

        .Logo img {
            height: 90px; 
            width: auto; 
        }

        .sidebar {
            width: 200px;
            background-color: #2c3e50;
            color: white;
            position: fixed;
            top: 120px;
            left: 0;
            bottom: 0;
            padding: 20px 0;
            box-sizing: border-box;
            transition: transform 0.3s ease;
        }

        .sidebar.collapsed {
            transform: translateX(-200px);
        }

        .sidebartitle {
            color: white;
            font-size: 1.4rem;
            margin-bottom: 20px;
            padding: 0 20px;
        }

        .menuitems {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 0;
            margin: 0;
            list-style: none;
        }

        .menuitem {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            padding: 14px 18px;
            color: white;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .menuitems a {
        text-decoration: none;
        color: inherit;
        }

        .menuitem:hover {
            background-color: #1a0966;
        }

        .menuitem.active {
            background-color: #1abc9c;
            font-weight: 500;
        }

        .togglebutton {
            background-color:rgb(16, 8, 54);
            color: white;
            border: 1px solid rgba(18, 1, 63, 0.3);
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .togglebutton:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .logoutbutton {
            background-color: rgba(255, 0, 0, 0.2);
            color: white;
            border: 1px solid rgba(255, 0, 0, 0.3);
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .profilebutton {
            background-color: rgba(46, 204, 113, 0.2);
            color: white;
            border: 1px solid rgba(46, 204, 113, 0.3);
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            text-decoration: none;
        }

        .profilebutton:hover {
            background-color: rgba(46, 204, 113, 0.3);
        }
        
         .maincontent {
            margin-left: 240px;
            margin-top: 100px;
            padding: 40px;
            flex: 1;
            box-sizing: border-box;
            transition: margin-left 0.3s ease;
        }

        .maincontent.expanded {
            margin-left: 0;
        }

        label {
            display: block;
            margin: 15px 0 5px;
            color: #1f2d3d;
            font-weight: 500;
        }
        select {
            width: 100%;
            padding: 12px 16px;
            font-size: 1rem;
            border: 1px solid #ccc;
            border-radius: 8px;
            background-color: #fff;
            color: #333;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: border 0.3s, box-shadow 0.3s;
            appearance: none; /* Remove default arrow styling for custom dropdown */
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg fill='gray' height='20' viewBox='0 0 24 24' width='20' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 20px;
        }

        select:focus {
            border-color: #1abc9c;
            box-shadow: 0 0 0 3px rgba(26, 188, 156, 0.2);
            outline: none;
        }

        .input-time-wrapper {
            position: relative;
            margin-bottom: 20px;
        }

        .input-time-wrapper i {
            position: absolute;
            top: 50%;
            right: 16px;
            transform: translateY(-50%);
            color: #888;
            pointer-events: none;
        }

        input[type="time"] {
            width: 100%;
            padding: 12px 44px 12px 16px;
            font-size: 1rem;
            border: 1px solid #ccc;
            border-radius: 8px;
            background-color: #fff;
            color: #333;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        input[type="time"]:focus {
            border-color: #1abc9c;
            box-shadow: 0 0 0 3px rgba(26, 188, 156, 0.2);
            outline: none;
        }


        .btn-delete, .btn-update {
            padding: 6px 14px;
            border: none;
            border-radius: 5px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            margin-right: 5px;
            display: inline-block;
        }

        .btn-delete {
            background-color: #e74c3c;
            color: white;
            border: 1px solid #c0392b;
        }

        .btn-delete:hover {
            background-color: #c0392b;
        }

        .btn-update {
            background-color: #3498db;
            color: white;
            border: 1px solid #2980b9;
        }

        .btn-update:hover {
            background-color: #2980b9;
        }

        .btn-submit {
            margin-top: 15px;
            padding: 10px 18px;
            background-color: #1abc9c;
            border: none;
            border-radius: 6px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .btn-submit:hover {
            background-color: #16a085;
        }


        .content {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 25px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            
        }

        .content h1 {
            font-size: 1.5rem;
            margin: 0;
            color: black;
            font-weight: 600;
        }

        .seccontent {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }

        .footer
        {
            background-color: #1f2d3d;
            color: white;
        }

    </style>
</head>
<body>
    <header class="header">
        <div class="header-left">
            <button class="togglebutton" id="togglebutton">
                <i class="fas fa-bars"></i>Menu 
            </button>
            <div class="Logo">    
                <img src="Image/UMPSALogo.png" alt="LogoUMP">
                <img src="Image/PetakomLogo.png" alt="LogoPetakom">
            </div>
        </div>
        <div class="header-right">
        <a href="EventAdvisorProfile.php" class="profilebutton">
    <i class="fas fa-user-circle"></i> My Profile
</a>
<a href="logout.php" class="logoutbutton" onclick="return confirm('Are you sure you want to log out?');">
  <i class="fas fa-sign-out-alt"></i> Logout
</a>
        </div>
    </header>

    <nav class="sidebar" id="sidebar">
        <h2 class="sidebartitle">Event Advisor</h2>
        <ul class="menuitems">
        <li>
                <a href="EventAdvisorDashboard.php" class="menuitem">
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="EventRegistration.php" class="menuitem">
                    <span>Event Registration</span>
                </a>
            </li>
            <li>
                <a href="EventInformation.php" class="menuitem">
                    <span>Event Information</span>
                </a>
            </li>
            <li>
                <a href="Event.php" class="menuitem">
                    <span>Event</span>
                </a>
            </li>
            <li>
                <a href="MeritClaim.php" class="menuitem">
                    <span>Merit Claim</span>
                </a>
            </li>
            <li>
                <a href="AttendanceSlot.php" class="menuitem active">
                    <span>Event attendance Slot</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="maincontent" id="maincontent">
        <div class="content">
    <h1>Create Attendance Slot</h1>
    <form method="POST" action="">
        <label for="event_id">Event:</label>
        <select name="event_id" id="event_id" required>
            <option value="">-- Select Event --</option>
                <?php while($row = $event_result->fetch_assoc()) { ?>
            <option value="<?= $row['EventID'] ?>" <?= isset($event_id) && $event_id == $row['EventID'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($row['EventTitle']) ?>
            </option>
            <?php } ?>
        </select>

        <label for="start_time">Slot Start Time:</label>
        <input type="time" name="StartTime" id="StartTime" required>

        <label for="end_time">Slot End Time:</label>
        <input type="time" name="EndTime" id="Endtime">
        <br>
        <button type="submit" class="btn-submit">Add Slot</button>
    </form>

    <?php if ($slots_result && $slots_result->num_rows > 0): ?>
    <h2>Existing Attendance Slots</h2>

    <!-- search function -->
    <form method="GET" action="" style="margin-top: 30px;">
    <label for="search_event">Search Attendance Slot:</label>
    <input type="text" name="search_event" id="search_event" value="<?= isset($_GET['search_event']) ? htmlspecialchars($_GET['search_event']) : '' ?>" placeholder="Enter event title..." style="padding: 8px; width: 300px; margin-right: 10px;">
    
    <button type="submit" class="btn-submit">Search</button>
    <a href="AttendanceSlot.php" class="btn-delete" style="text-decoration: none;">Clear</a>
    </form>

    <!-- dislay attendance slot created -->
    <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
        <thead>
            <tr style="background-color: #f2f2f2;">
                <th style="padding: 12px; border: 1px solid #ccc;">Slot ID</th>
                <th style="padding: 12px; border: 1px solid #ccc;">Event Title</th>
                <th style="padding: 12px; border: 1px solid #ccc;">Event Venue</th>
                <th style="padding: 12px; border: 1px solid #ccc;">Start Time</th>
                <th style="padding: 12px; border: 1px solid #ccc;">End Time</th>
                <th style="padding: 12px; border: 1px solid #ccc;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($slot = $slots_result->fetch_assoc()): ?>
                <tr>
                    <td style="padding: 12px; border: 1px solid #ccc;"><?= htmlspecialchars($slot['SlotID']) ?></td>
                    <td style="padding: 12px; border: 1px solid #ccc;"><?= htmlspecialchars($slot['EventTitle']) ?></td>
                    <td style="padding: 12px; border: 1px solid #ccc;"><?= htmlspecialchars($slot['EventVenue']) ?></td>
                    <td style="padding: 12px; border: 1px solid #ccc;"><?= htmlspecialchars(date("h:i A", strtotime($slot['StartTime']))) ?></td>
                    <td style="padding: 12px; border: 1px solid #ccc;"><?= htmlspecialchars(date("h:i A", strtotime($slot['EndTime']))) ?></td>
                    <td style="padding: 12px; border: 1px solid #ccc;">
                        <form method="POST" action="AttendanceSlot_DeleteSlot.php" onsubmit="return confirm('Are you sure?');" style="display:inline;">
                            <input type="hidden" name="slot_id" value="<?= htmlspecialchars($slot['SlotID']) ?>">
                            <button type="submit" class="btn-delete">Delete</button>
                        </form>
                        <a href="AttendanceSlot_UpdateSlot.php?slot_id=<?= urlencode($slot['SlotID']) ?>" class="btn-update">Update</a>
                        <?php if (!empty($slot['QRCodeID']) && !empty($slot['Image_URL'])): ?>
                            <a href="<?= htmlspecialchars($slot['Image_URL']) ?>" target="_blank" class="btn-update" style="background-color: #2ecc71; border-color: #27ae60;">View QR</a>
                        <?php endif; ?>
                        <a href="AttendanceSlot_ViewAttendance.php?slot_id=<?= urlencode($slot['SlotID']) ?>" class="btn-update" style="background-color: #3498db; border-color: #2980b9; margin-left:5px;">View Attendance</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
<?php else: ?>
    <p style="margin-top: 20px;">No attendance slots created yet.</p>
<?php endif; ?>


    <div class="footer">
            <footer>
                <center><p>&copy; 2025 MyPetakom</p></center>
            </footer>
        </div>
</body>
</html>