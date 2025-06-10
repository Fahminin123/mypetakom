<?php
$conn = new mysqli("localhost", "root", "", "mypetakom");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['slot_id'])) {
    $slot_id = $_GET['slot_id'];

    $stmt = $conn->prepare("SELECT SlotID, StartTime, EndTime FROM AttendanceSlot WHERE SlotID = ?");
    $stmt->bind_param("s", $slot_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        die("Slot not found.");
    }

    $slot = $result->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['slot_id'], $_POST['start_time'], $_POST['end_time'])) {
    $slot_id = $_POST['slot_id'];
    $new_start_time = $_POST['start_time']; // only HH:MM
    $new_end_time = $_POST['end_time'];     // only HH:MM

    // Get original date parts from DB
    $stmt = $conn->prepare("SELECT StartTime, EndTime FROM AttendanceSlot WHERE SlotID = ?");
    $stmt->bind_param("s", $slot_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        die("Slot not found.");
    }
    $slot = $result->fetch_assoc();

    $start_date = date('Y-m-d', strtotime($slot['StartTime']));
    $end_date = date('Y-m-d', strtotime($slot['EndTime']));

    // Merge date + time
    $start_datetime = "$start_date $new_start_time:00";
    $end_datetime = "$end_date $new_end_time:00";

    if (strtotime($start_datetime) >= strtotime($end_datetime)) {
        echo "<script>alert('Start time must be before end time.'); window.history.back();</script>";
        exit;
    }

    $update_stmt = $conn->prepare("UPDATE AttendanceSlot SET StartTime = ?, EndTime = ? WHERE SlotID = ?");
    $update_stmt->bind_param("sss", $start_datetime, $end_datetime, $slot_id);

    if ($update_stmt->execute()) {
        header("Location: attendanceSlot.php?slot_id=" . urlencode($slot_id) . "&updated=1");
        exit;
    } else {
        echo "<script>alert('Failed to update slot.');</script>";
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
        input[type="datetime-local"] {
            width: 100%;
            padding: 10px 12px;
            font-size: 1rem;
            border-radius: 6px;
            border: 1px solid #ddd;
            box-sizing: border-box;
        }

        button {
            margin-top: 25px;
            width: 100%;
            padding: 12px;
            background-color: #1abc9c;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        button:hover {
            background-color: #159e88;
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
    <script>
        function confirmUpdate() {
            return confirm('Are you sure you want to update the slot times?');
        }
    </script>
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
    <h1>Update Attendance Slot</h1>
        <form method="POST" action="attendanceSlot_updateSlot.php" onsubmit="return confirmUpdate()">
            <input type="hidden" name="slot_id" value="<?= htmlspecialchars($slot['SlotID']) ?>">

            <label for="start_time">Start Time:</label>
            <input type="time" name="start_time" id="start_time" required value="<?= date('H:i', strtotime($slot['StartTime'])) ?>">

            <label for="end_time">End Time:</label>
            <input type="time" name="end_time" id="end_time" required value="<?= date('H:i', strtotime($slot['EndTime'])) ?>">

            <button type="submit">Update Slot</button>
        </form>

    <div class="footer">
            <footer>
                <center><p>&copy; 2025 MyPetakom</p></center>
            </footer>
        </div>
</body>
</html>