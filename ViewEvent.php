<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "mypetakom");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Restrict access to only logged-in event advisors
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get event ID from URL
$event_id = isset($_GET['id']) ? $_GET['id'] : null;
if (!$event_id) {
    header("Location: Event.php");
    exit();
}

// Get event details
$event_query = "SELECT e.*, s.StaffName 
                FROM event e 
                JOIN staff s ON e.StaffID = s.StaffID 
                WHERE e.EventID = ?";
$event_stmt = $conn->prepare($event_query);
$event_stmt->bind_param("s", $event_id);
$event_stmt->execute();
$event_result = $event_stmt->get_result();

if ($event_result->num_rows === 0) {
    header("Location: Event.php");
    exit();
}
$event = $event_result->fetch_assoc();

// Handle committee deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_member'])) {
    $committee_id = $_POST['committee_id'];
    $delete_query = "DELETE FROM eventcommittee WHERE CommitteeID = ?";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param("s", $committee_id);
    $delete_stmt->execute();

    // Refresh page
    header("Location: ViewEvent.php?id=$event_id");
    exit();
}

// Get committee members for this event (with their assigned roles)
$committee_query = "SELECT c.CommitteeID, s.StudentName, cr.CR_Desc as Role
                    FROM eventcommittee c
                    JOIN student s ON c.StudentID = s.StudentID
                    JOIN committeerole cr ON c.CR_ID = cr.CR_ID
                    WHERE c.EventID = ?";
$committee_stmt = $conn->prepare($committee_query);
$committee_stmt->bind_param("s", $event_id);
$committee_stmt->execute();
$committee_result = $committee_stmt->get_result();
$committee_members = $committee_result->fetch_all(MYSQLI_ASSOC);

// Get Merit Application status for this event (if any)
$merit_status = null;
$merit_query = "SELECT MeritApplicationStatus FROM meritapplication WHERE EventID = ?";
$merit_stmt = $conn->prepare($merit_query);
$merit_stmt->bind_param("s", $event_id);
$merit_stmt->execute();
$merit_result = $merit_stmt->get_result();
if ($row = $merit_result->fetch_assoc()) {
    $merit_status = $row['MeritApplicationStatus'];
}

if (isset($_POST['apply_merit'])) {
    // Prevent duplicate applications for the same event
    $check_query = "SELECT * FROM meritapplication WHERE EventID = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("s", $event_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows == 0) {
        // Generate Merit Application ID
        $meritApplicationID = "MA" . uniqid();

        $merit_query = "INSERT INTO meritapplication (MeritApplicationID, StaffID, EventID, MeritApplicationStatus) 
                        VALUES (?, NULL, ?, 'Pending')";
        $merit_stmt = $conn->prepare($merit_query);
        $merit_stmt->bind_param("ss", $meritApplicationID, $event_id);
        $merit_stmt->execute();
        $merit_status = 'Pending';
    }
    header("Location: ViewEvent.php?id=$event_id&merit=applied");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Event</title>
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
        .eventview {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .eventsection {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .sectionlabel {
            width: 200px;
            font-weight: 600;
            color: #333;
        }
        .sectioncontent {
            flex: 1;
            color: #555;
        }
        .statusbadge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .active {
            background-color: #cce5ff;
            color: #004085;
        }
        .postponed {
            background-color: #ffe6a3;
            color: #8a6d1e;
        }
        .cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        .completed {
            background-color: #e2e3e5;
            color: #383d41;
        }
        .approved {
            background-color: #d4edda;
            color: #155724;
        }
        .rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        .meritapplication {
            padding: 15px 0;
            display: flex;
            justify-content: flex-end;
        }
        .meritapplicationbutton {
            padding: 8px 15px;
            background-color: #1abc9c;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .committeetable {
            width: 100%;
            border-collapse: collapse;
        }
        .committeetable th {
            background-color: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e0e0e0;
        }
        .committeetable td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }
        .committeetable tr:last-child td {
            border-bottom: none;
        }
        .committeetable tr:hover {
            background-color: #f5f5f5;
        }
        .action {
            display: flex;
            gap: 8px;
        }
        .addcommitteerow {
            display: flex;
            justify-content: flex-end;
            padding: 15px 0;
            margin-top: 10px;
        }
        .addcommitteebutton {
            padding: 8px 15px;
            background-color: #1f2d3d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.2s;
            text-decoration: none;
        }
        .footer {
            background-color: #1f2d3d;
            color: white;
        }

        .meritstatusbadge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-left: 0;
            margin-right: 0;
            display: inline-block;
        }
        .merit-pending {
            background-color: #ffe6a3;
            color: #8a6d1e;
        }
        .merit-approved, .merit-accepted {
            background-color: #b1e5c9;
            color: #187244;
        }
        .merit-rejected {
            background-color: #fdc6c6;
            color: #892e2e;
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
                <a href="Event.php" class="menuitem active">
                    <span>Event</span>
                </a>
            </li>
            <li>
                <a href="MeritClaim.php" class="menuitem">
                    <span>Merit Claim</span>
                </a>
            </li>
            <li>
                <a href="AttendanceSlot.php" class="menuitem">
                    <span>Event attendance Slot</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="maincontent" id="maincontent">
        <div class="content">
            <h1>View Event</h1>
            <?php if (isset($_GET['merit']) && $_GET['merit'] == 'applied'): ?>
                <div class="message success">Merit application submitted successfully!</div>
            <?php endif; ?>
        </div>

        <div class="seccontent">
            <div class="eventview">
                <div class="eventsection">
                    <div class="sectionlabel">Event Title</div>
                    <div class="sectioncontent"><?= htmlspecialchars($event['EventTitle']) ?></div>
                </div>
                <div class="eventsection">
                    <div class="sectionlabel">Event Venue</div>
                    <div class="sectioncontent"><?= htmlspecialchars($event['EventVenue']) ?></div>
                </div>
                <div class="eventsection">
                    <div class="sectionlabel">Event Date and Time</div>
                    <div class="sectioncontent"><?= date('j F Y, g:i A', strtotime($event['EventDateandTime'])) ?></div>
                </div>
                <div class="eventsection">
                    <div class="sectionlabel">Status</div>
                    <div class="sectioncontent">
                        <span class="statusbadge <?= strtolower($event['EventStatus']) ?>">
                            <?= $event['EventStatus'] ?>
                        </span>
                    </div>
                </div>
                <div class="eventsection">
                    <div class="sectionlabel">Merit Application Status</div>
                    <div class="sectioncontent">
                        <?php if ($merit_status): ?>
                            <?php
                                $merit_class = '';
                                switch (strtolower($merit_status)) {
                                    case 'pending':
                                        $merit_class = 'merit-pending';
                                        break;
                                    case 'approved':
                                    case 'accepted':
                                        $merit_class = 'merit-approved';
                                        break;
                                    case 'rejected':
                                        $merit_class = 'merit-rejected';
                                        break;
                                    default:
                                        $merit_class = 'merit-pending';
                                }
                            ?>
                            <span class="meritstatusbadge <?= $merit_class ?>">
                                <?= htmlspecialchars($merit_status) ?>
                            </span>
                        <?php else: ?>
                            <span style="color: #888;">No application yet</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="meritapplication">
                    <?php if (!$merit_status): ?>
                        <form method="POST" action="ViewEvent.php?id=<?= $event_id ?>">
                            <button type="submit" name="apply_merit" class="meritapplicationbutton">
                                Apply Merit Application
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <h2>Event Committee</h2>
            <table class="committeetable">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Committee Role</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($committee_members as $member): ?>
                    <tr>
                        <td><?= htmlspecialchars($member['StudentName']) ?></td>
                        <td><?= htmlspecialchars($member['Role']) ?></td>
                        <td class="action">
                            <form method="POST" action="ViewEvent.php?id=<?= $event_id ?>">
                                <input type="hidden" name="committee_id" value="<?= $member['CommitteeID'] ?>">
                                <button type="submit" name="delete_member" class="deletebutton" onclick="return confirm('Are you sure you want to delete this committee member?');">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="addcommitteerow">
                <a href="AddCommittee.php?event_id=<?= $event_id ?>" class="addcommitteebutton">
                    <i class="fas fa-plus"></i> Add Committee Member
                </a>
            </div>
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