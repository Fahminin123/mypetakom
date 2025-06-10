<?php
// Connect to DB
$conn = new mysqli("localhost", "root", "", "mypetakom");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_GET['slot_id'])) {
    die("No slot ID provided.");
}

$slot_id = $_GET['slot_id'];

// Optional: Get slot details for header
$slot_sql = "SELECT SlotID, EventID, StartTime, EndTime FROM AttendanceSlot WHERE SlotID = ?";
$stmt = $conn->prepare($slot_sql);
$stmt->bind_param("s", $slot_id);
$stmt->execute();
$slot_result = $stmt->get_result();

if ($slot_result->num_rows == 0) {
    die("Slot not found.");
}

$slot = $slot_result->fetch_assoc();

// Get attendance submissions for this slot
$attendance_sql = "SELECT attendance.StudentID, attendance.CheckInTime, attendance.ActualGeolocation
                   FROM attendance
                   JOIN AttendanceSlot ON attendance.SlotID = AttendanceSlot.SlotID
                   WHERE attendance.SlotID = ?
                   ORDER BY attendance.CheckInTime DESC";
$attendance_stmt = $conn->prepare($attendance_sql);
$attendance_stmt->bind_param("s", $slot_id);
$attendance_stmt->execute();
$attendance_result = $attendance_stmt->get_result();
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

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table, th, td {
            border: 1px solid #ddd;
        }

        th, td {
            padding: 12px;
            text-align: left;
        }

        th {
            background-color: #f0f0f0;
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
    <h1>Attendance Submissions for Slot <?= htmlspecialchars($slot_id) ?></h1>
    <p>Start Time: <?= htmlspecialchars($slot['StartTime']) ?><br>
       End Time: <?= htmlspecialchars($slot['EndTime']) ?></p>

        <a href="AttendanceSlot.php" class="togglebutton" style="text-decoration: none; margin-bottom: 20px; display: inline-flex; align-items: center;">
        <i class="fas fa-arrow-left"></i> Back to Attendance Slots</a>


    <?php if ($attendance_result->num_rows > 0): ?>
    <table>
        <thead>
            <tr>
                <th>Student ID</th>
                <th>Check-In Time</th>
                <th>Geolocation</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $attendance_result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['StudentID']) ?></td>
                    <td><?= htmlspecialchars($row['CheckInTime']) ?></td>
                    <td><a href="https://maps.google.com/?q=<?= urlencode($row['ActualGeolocation']) ?>" target="_blank"><?= htmlspecialchars($row['ActualGeolocation']) ?></a></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p>No attendance submissions found for this slot.</p>
    <?php endif; ?>

    <div class="footer">
            <footer>
                <center><p>&copy; 2025 MyPetakom</p></center>
            </footer>
        </div>
</body>
</html>