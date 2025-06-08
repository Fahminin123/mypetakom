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
    // Student not found in database
    session_destroy();
    header("Location: login.php");
    exit();
}

$student = $result->fetch_assoc();

// Get eventid from URL
$event_id = $_GET['eventid'] ?? '';
if (!$event_id) {
    echo "<div style='padding:40px;text-align:center;'>Invalid event.</div>";
    exit();
}

// Get event details
$event_query = "SELECT * FROM event WHERE EventID = ?";
$stmt = $conn->prepare($event_query);
$stmt->bind_param("s", $event_id);
$stmt->execute();
$event_result = $stmt->get_result();
$event = $event_result->fetch_assoc();
$stmt->close();

if (!$event) {
    echo "<div style='padding:40px;text-align:center;'>Event not found.</div>";
    exit();
}

// Get event advisor (staff) details
$staff_query = "SELECT * FROM staff WHERE StaffID = ?";
$stmt = $conn->prepare($staff_query);
$stmt->bind_param("s", $event['StaffID']);
$stmt->execute();
$staff_result = $stmt->get_result();
$staff = $staff_result->fetch_assoc();
$stmt->close();

// Get merit application status for this event
$merit_query = "SELECT MeritApplicationStatus FROM meritapplication WHERE EventID = ?";
$stmt = $conn->prepare($merit_query);
$stmt->bind_param("s", $event_id);
$stmt->execute();
$merit_result = $stmt->get_result();
$merit_status = null;
if ($row = $merit_result->fetch_assoc()) {
    $merit_status = $row['MeritApplicationStatus'];
}
$stmt->close();

// Get list of committee members and their roles
$committee_query = "
    SELECT ec.StudentID, s.StudentName, s.StudentContact, cr.CR_Desc AS CommitteeRole
    FROM eventcommittee ec
    JOIN student s ON ec.StudentID = s.StudentID
    LEFT JOIN committeerole cr ON ec.CR_ID = cr.CR_ID
    WHERE ec.EventID = ?
";
$stmt = $conn->prepare($committee_query);
$stmt->bind_param("s", $event_id);
$stmt->execute();
$committee_result = $stmt->get_result();
$committee_members = [];
while ($row = $committee_result->fetch_assoc()) {
    $committee_members[] = $row;
}
$stmt->close();

// Process merit status message and badge
$merit_message = "";
$merit_class = "";
if ($merit_status) {
    switch (strtolower($merit_status)) {
        case 'approved':
        case 'accepted':
            $merit_message = "This Event Provide Merit";
            $merit_class = "merit-approved";
            break;
        case 'rejected':
            $merit_message = "This Event Not Provide Merit";
            $merit_class = "merit-rejected";
            break;
        case 'pending':
        default:
            $merit_message = "Merit Application still in progress";
            $merit_class = "merit-pending";
            break;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>View Event Details for Student</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
            background-color: #5e3c99;;
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

        .seccontent {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }

        .event-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 14px;
            color: #1f2d3d;
            text-align: center;
        }
        .event-info-list {
            list-style: none;
            padding: 0;
            margin: 0 0 18px 0;
        }
        .event-info-list li {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 14px;
            border-bottom: 1px solid #f0f2f5;
            padding-bottom: 10px;
        }
        .event-label {
            font-weight: 500;
            color: #34495e;
            font-size: 1.07rem;
            flex: 0 0 50%;
        }
        .event-value {
            color: #222;
            font-size: 1.06rem;
            text-align: right;
        }
        .statusbadge {
            display: inline-block;
            min-width: 90px;
            padding: 4px 14px;
            border-radius: 4px;
            font-size: 0.97em;
            font-weight: 500;
            text-align: center;
        }
        .pending { 
            background: #fff3cd; 
            color: #856404; 
        }
        .active {
            background: #cce5ff; 
            color: #004085; 
        }
        .postponed { 
            background: #ffe6a3; 
            color: #8a6d1e; 
        }
        .cancelled { 
            background: #f8d7da; 
            color: #721c24; 
        }
        .completed { 
            background: #e2e3e5; 
            color: #383d41; 
        }

        .merit-status {
            margin: 18px 0 0 0;
            padding: 13px;
            font-size: 1.13rem;
            border-radius: 7px;
            text-align: center;
            font-weight: 600;
        }
        .merit-approved {
            background: #e1fbe1;
            color: #1e7d22;
            border: 1px solid #baf2c7;
        }
        .merit-rejected {
            background: #ffe1e1;
            color: #b53c3c;
            border: 1px solid #ffc0c0;
        }
        .merit-pending {
            background: #fff9d8;
            color: #b2932a;
            border: 1px solid #f6e6a9;
        }
        .section-title {
            margin: 28px 0 10px 0;
            font-size: 1.12rem;
            font-weight: 600;
            color: #5e3c99;
            border-left: 5px solid #c7b7e6;
            padding-left: 10px;
        }
        .advisor-info, .committee-list {
            margin: 0 0 10px 0;
            padding: 0;
        }
        .advisor-info li, .committee-list li {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 9px;
        }
        .advisor-label, .committee-label {
            font-weight: 500;
            color: #444;
            font-size: 1.02rem;
        }
        .advisor-value, .committee-value {
            color: #222;
            font-size: 1.01rem;
            text-align: right;
        }
        .committee-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .committee-table th, .committee-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #ececec;
            text-align: left;
        }
        .committee-table th {
            background: #ede7f6;
            color: #4c2a7a;
            font-weight: 600;
        }
        .committee-table tr:last-child td {
            border-bottom: none;
        }
        .committee-role-badge {
            background: #f3e9fc;
            color: #7e57c2;
            border-radius: 4px;
            padding: 3px 10px;
            font-size: .97em;
        }

        .back-btn {
            display: inline-block;
            margin-bottom: 18px;
            background: #5e3c99;
            color: #fff;
            border: none;
            border-radius: 5px;
            padding: 8px 20px;
            font-size: 1em;
            cursor: pointer;
            text-decoration: none;
        }
        .back-btn:hover {
            background: #9575cd;
            color: #fff;
        }

        .footer
        {
            background-color: #3a1d6e; 
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
                <a href="StudentDashboard.php" class="menuitem">
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="StudEvent.php" class="menuitem">
                    <span>Event</span>
                </a>
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
                <a href="MissingMerit.php" class="menuitem">
                    <span>Missing Merit</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="maincontent" id="maincontent">
        <a href="StudEvent.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
        <div class="content">
            <div class="event-title"><?= htmlspecialchars($event['EventTitle']); ?></div>
            <ul class="event-info-list">
                <li>
                    <span class="event-label">Event Name</span>
                    <span class="event-value"><?= htmlspecialchars($event['EventTitle']); ?></span>
                </li>
                <li>
                    <span class="event-label">Venue</span>
                    <span class="event-value"><?= htmlspecialchars($event['EventVenue']); ?></span>
                </li>
                <li>
                    <span class="event-label">Date &amp; Time</span>
                    <span class="event-value"><?= date('j F Y, g:i A', strtotime($event['EventDateandTime'])); ?></span>
                </li>
                <li>
                    <span class="event-label">Event Status</span>
                    <?php
                        $status = strtolower($event['EventStatus']);
                        $statusClass = "";
                        switch($status) {
                            case 'pending': $statusClass = "pending"; break;
                            case 'active': $statusClass = "active"; break;
                            case 'postponed': $statusClass = "postponed"; break;
                            case 'cancelled': $statusClass = "cancelled"; break;
                            case 'completed': $statusClass = "completed"; break;
                            default: $statusClass = "pending"; break;
                        }
                    ?>
                    <span class="event-value statusbadge <?= $statusClass ?>">
                        <?= htmlspecialchars($event['EventStatus']); ?>
                    </span>
                </li>
            </ul>
            <?php if ($merit_message): ?>
                <div class="merit-status <?= $merit_class ?>">
                    <?= $merit_message ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="seccontent">
            <div class="section-title"><i class="fas fa-user-tie"></i> Event Advisor</div>
            <ul class="advisor-info">
                <li>
                    <span class="advisor-label">Name</span>
                    <span class="advisor-value"><?= htmlspecialchars($staff['StaffName'] ?? '-'); ?></span>
                </li>
                <li>
                    <span class="advisor-label">Contact</span>
                    <span class="advisor-value"><?= htmlspecialchars($staff['StaffContact'] ?? '-'); ?></span>
                </li>
                <li>
                    <span class="advisor-label">Email</span>
                    <span class="advisor-value"><?= htmlspecialchars($staff['StaffEmail'] ?? '-'); ?></span>
                </li>
            </ul>
            <div class="section-title"><i class="fas fa-users"></i> Committee Members</div>
            <table class="committee-table">
                <thead>
                    <tr>
                        <th>StudentID</th>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Role</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($committee_members) > 0): ?>
                    <?php foreach ($committee_members as $member): ?>
                        <tr>
                            <td><?= htmlspecialchars($member['StudentID']); ?></td>
                            <td><?= htmlspecialchars($member['StudentName']); ?></td>
                            <td><?= htmlspecialchars($member['StudentContact']); ?></td>
                            <td>
                                <span class="committee-role-badge"><?= htmlspecialchars($member['CommitteeRole'] ?? '-'); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align:center; color:#888;">No committee members found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
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