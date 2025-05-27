<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "mypetakom");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Restrict access to only logged-in students
if (!isset($_SESSION['user_id']) || $_SESSION['type_user'] !== 'student') {
    header("Location: login.php");
    exit();
}

// Get student data from database
$student_id = $_SESSION['user_id'];
$query = "SELECT * FROM student WHERE StudentID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$student = $result->fetch_assoc();

// Handle deleting a draft claim
if (isset($_GET['delete_claim_id'])) {
    $delete_claim_id = $_GET['delete_claim_id'];
    $del_stmt = $conn->prepare("DELETE FROM meritclaim WHERE ClaimID = ? AND StudentID = ? AND MeritClaimStatus = 'Draft'");
    $del_stmt->bind_param("ss", $delete_claim_id, $student_id);
    $del_stmt->execute();
    $del_stmt->close();
    header("Location: MissingMerit.php");
    exit();
}

// Get all events attended by the student (via attendance, attendanceslot, event)
$events_query = "SELECT e.EventID, e.EventTitle, e.EventDateandTime, e.EventVenue
                 FROM attendance a
                 JOIN attendanceslot s ON a.SlotID = s.SlotID
                 JOIN event e ON s.EventID = e.EventID
                 WHERE a.StudentID = ?";
$events_stmt = $conn->prepare($events_query);
$events_stmt->bind_param("s", $student_id);
$events_stmt->execute();
$events_result = $events_stmt->get_result();

$attended_events = [];
while ($row = $events_result->fetch_assoc()) {
    $attended_events[] = $row;
}

// Get existing merit claims (not rejected)
$claims_query = "SELECT EventID FROM meritclaim WHERE StudentID = ? AND MeritClaimStatus != 'Rejected'";
$claims_stmt = $conn->prepare($claims_query);
$claims_stmt->bind_param("s", $student_id);
$claims_stmt->execute();
$claims_result = $claims_stmt->get_result();

$claimed_events = [];
while ($row = $claims_result->fetch_assoc()) {
    $claimed_events[] = $row['EventID'];
}

// Filter out events already claimed
$eligible_events = array_filter($attended_events, function($event) use ($claimed_events) {
    return !in_array($event['EventID'], $claimed_events);
});

// Fetch merit claims for the logged-in student and join with event for event details
$merit_query = "SELECT mc.ClaimID, mc.EventID, e.EventTitle, mc.MeritClaimStatus, mc.DateSubmitted
                FROM meritclaim mc
                JOIN event e ON mc.EventID = e.EventID
                WHERE mc.StudentID = ?";
$merit_stmt = $conn->prepare($merit_query);
$merit_stmt->bind_param("s", $student_id);
$merit_stmt->execute();
$merit_result = $merit_stmt->get_result();

$missing_merits = [];
if ($merit_result->num_rows > 0) {
    while ($row = $merit_result->fetch_assoc()) {
        $missing_merits[] = [
            'ClaimID' => $row['ClaimID'],
            'EventID' => $row['EventID'],
            'EventTitle' => $row['EventTitle'],
            'MeritClaimStatus' => $row['MeritClaimStatus'],
            'DateSubmitted' => $row['DateSubmitted']
        ];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Student Dashboard - Missing Merit</title>
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
            background-color:rgb(31, 8, 77); 
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
            background-color: #5e3c99;
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
            background-color: #7e57c2;
        }
        .menuitem.active {
            background-color: #9575cd;
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
        .merit-table, .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 35px;
            background: #fff;
        }
        .merit-table th, .merit-table td, .info-table th, .info-table td {
            border: 1px solid #c9c9c9;
            padding: 8px 12px;
            text-align: center;
        }
        .merit-table th {
            background: #7e57c2;
            color: #fff;
        }
        .merit-table tr:nth-child(even), .info-table tr:nth-child(even) {
            background: #f9f6ff;
        }
        .info-table th {
            background: #3a1d6e;
            color: #fff;
        }
        .events-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .events-table th, .events-table td {
            padding: 12px 15px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .events-table th {
            background-color: #5e3c99;
            color: white;
        }
        .events-table tr:nth-child(even) {
            background-color: #f9f6ff;
        }
        .claim-btn, .update-btn, .delete-btn {
            background-color: #388e3c;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
            margin: 2px;
        }
        .update-btn {
            background-color: #ffa726;
        }
        .update-btn:hover {
            background-color: #fb8c00;
        }
        .delete-btn {
            background-color: #e57373;
        }
        .delete-btn:hover {
            background-color: #d32f2f;
        }
        .claim-btn:hover {
            background-color: #7e57c2;
        }
        .no-events {
            padding: 20px;
            background-color: #f5f5f5;
            border-radius: 5px;
            text-align: center;
            color: #666;
        }
        .no-missing {
            color: green;
            font-weight: bold;
            padding: 16px;
            background: #e7ffe7;
            border-radius: 6px;
            margin-top: 16px;
            display: inline-block;
        }
        /* Add spacing between tables */
        .table-spacing {
            margin-bottom: 45px;
        }
        .footer {
            background-color: #3a1d6e; 
            color: white;
            position: relative;
            bottom: 0;
            width: 100%;
            margin-top: 30px;
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
                <img src="image/UMPSALogo.png" alt="LogoUMP">
                <img src="image/PetakomLogo.png" alt="LogoPetakom">
            </div>
        </div>
        <div class="header-right">
        <a href="StudentProfile.php" class="profilebutton">
            <i class="fas fa-user-circle"></i> My Profile
        </a>
        <a href="logout.php" class="logoutbutton" onclick="return confirm('Are you sure you want to log out?');">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
        </div>
    </header>
    <nav class="sidebar" id="sidebar">
        <h2 class="sidebartitle">Student</h2>
        <ul class="menuitems">
            <li>
                <a href="StudentDashboard.php" class="menuitem ">
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="StudEvent.php" class="menuitem">
                    <span>Event</span>
                </a>
            </li>
            <li>
                <a href="Membership.php" class="menuitem">
                    <span>Membership</span>
                </a>
            </li>
            <li>
                <a href="MeritAwarded.php" class="menuitem">
                    <span>Merit Awarded</span>
                </a>
            </li>
            <li>
                <a href="MissingMerit.php" class="menuitem active">
                    <span>Missing Merit</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="maincontent" id="maincontent">
        <div class="content">
            <h1>Missing Merit</h1>
        </div>
        <div class="seccontent">
            <h2>List of Event</h2>
            <?php if (empty($eligible_events)): ?>
                <div class="no-events table-spacing">
                    You have no events eligible for merit claims or you've already claimed merits for all attended events.
                </div>
            <?php else: ?>
                <table class="events-table table-spacing">
                    <thead>
                        <tr>
                            <th>Event Title</th>
                            <th>Date & Time</th>
                            <th>Venue</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($eligible_events as $event): ?>
                        <tr>
                            <td><?= htmlspecialchars($event['EventTitle']) ?></td>
                            <td><?= htmlspecialchars($event['EventDateandTime']) ?></td>
                            <td><?= htmlspecialchars($event['EventVenue']) ?></td>
                            <td>
                                <a href="ClaimMissingMerit.php?event_id=<?= urlencode($event['EventID']) ?>" class="claim-btn">
                                     Claim Merit
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2 style="margin-top:55px;">Merit Claims</h2>
            <?php if (empty($missing_merits)): ?>
                <div class="no-missing">No merit claim records found.</div>
            <?php else: ?>
                <table class="merit-table">
                    <thead>
                        <tr>
                            <th>Event Title</th>
                            <th>Date Submitted</th>
                            <th>Claim Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($missing_merits as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['EventTitle']) ?></td>
                            <td><?= htmlspecialchars($row['DateSubmitted']) ?></td>
                            <td><?= htmlspecialchars($row['MeritClaimStatus']) ?></td>
                            <td>
                            <?php if ($row['MeritClaimStatus'] === 'Draft'): ?>
                                <a href="ClaimMissingMerit.php?event_id=<?= urlencode($row['EventID']) ?>&update_claim_id=<?= urlencode($row['ClaimID']) ?>" class="update-btn">
                                    <i class="fas fa-edit"></i> Update Draft
                                </a>
                                <a href="MissingMerit.php?delete_claim_id=<?= urlencode($row['ClaimID']) ?>" class="delete-btn" onclick="return confirm('Are you sure you want to delete this draft claim?');">
                                    <i class="fas fa-trash"></i> Delete Draft
                                </a>
                            <?php else: ?>
                                <!-- No action for submitted claims -->
                                <span style="color:#888;">-</span>
                            <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleButton = document.getElementById('togglebutton');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('maincontent');
            toggleButton.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
                const icon = toggleButton.querySelector('i');
                if (sidebar.classList.contains('collapsed')) {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                    toggleButton.innerHTML = '<i class="fas fa-bars"></i> Menu';
                } else {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                    toggleButton.innerHTML = '<i class="fas fa-times"></i> Menu';
                }
            });
        });
    </script>
    <div class="footer">
        <footer>
            <center><p>&copy; 2025 MyPetakom</p></center>
        </footer>
    </div>
</body>
</html>