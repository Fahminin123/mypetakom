<?php
// Connect to the database
$link = mysqli_connect("localhost", "root", "", "mypetakom");
if (!$link) {
    die("Connection failed: " . mysqli_connect_error());
}

// Get all attendance records
$sql = "SELECT * FROM attendance ORDER BY CheckInTime DESC";
$result = mysqli_query($link, $sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Attendance Slot</title>
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

        .Logo img {
            height: 90px;
            width: auto;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-right: 20px;
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
        }

        .sidebartitle {
            color: white;
            font-size: 1.4rem;
            margin-bottom: 20px;
            padding: 0 20px;
        }

        .menuitems {
            list-style: none;
            padding: 0;
        }

        .menuitem {
            display: block;
            padding: 14px 20px;
            color: white;
            text-decoration: none;
            background-color: rgba(255,255,255,0.1);
            margin: 4px 0;
            border-radius: 6px;
        }

        .menuitem:hover {
            background-color: #1a0966;
        }

        .menuitem.active {
            background-color: #1abc9c;
        }

        .maincontent {
            margin-left: 240px;
            margin-top: 140px;
            padding: 40px;
            flex: 1;
            box-sizing: border-box;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            border: 1px solid #ccc;
            text-align: center;
        }

        th {
            background-color: #2c3e50;
            color: white;
        }

        tr:nth-child(even) {
            background-color: #f5f5f5;
        }

        .footer {
            background-color: #1f2d3d;
            color: white;
            text-align: center;
            padding: 10px 0;
            margin-top: auto;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <div class="Logo">
                <img src="Image/UMPSALogo.png" alt="UMP Logo">
                <img src="Image/PetakomLogo.png" alt="Petakom Logo">
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
    </div>

    <nav class="sidebar">
        <h2 class="sidebartitle">Event Advisor</h2>
        <ul class="menuitems">
            <li><a href="EventAdvisorDashboard.php" class="menuitem">Dashboard</a></li>
            <li><a href="EventRegistration.php" class="menuitem">Event Registration</a></li>
            <li><a href="EventInformation.php" class="menuitem">Event Information</a></li>
            <li><a href="Event.php" class="menuitem">Event</a></li>
            <li><a href="MeritClaim.php" class="menuitem">Merit Claim</a></li>
            <li><a href="AttendanceSlot.php" class="menuitem active">Event Attendance Slot</a></li>
        </ul>
    </nav>

    <div class="maincontent">
        <h1>All Attendance Submissions</h1>

        <table>
            <tr>
                <th>Attendance ID</th>
                <th>Student ID</th>
                <th>Slot ID</th>
                <th>Check-In Time</th>
                <th>Geo Location</th>
            </tr>
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['AttendanceID']); ?></td>
                        <td><?php echo htmlspecialchars($row['StudentID']); ?></td>
                        <td><?php echo htmlspecialchars($row['SlotID']); ?></td>
                        <td><?php echo htmlspecialchars($row['CheckInTime']); ?></td>
                        <td><?php echo htmlspecialchars($row['ActualGeolocation']); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5">No attendance records found.</td></tr>
            <?php endif; ?>
        </table>
    </div>

    <div class="footer">
        <footer>
            <p>&copy; 2025 MyPetakom</p>
        </footer>
    </div>
</body>
</html>

<?php
mysqli_close($link);
?>
