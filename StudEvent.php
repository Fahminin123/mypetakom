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

// Fetch events with committee role for the student
$committee_query = "
    SELECT 
        e.EventID, 
        e.EventTitle, 
        e.EventStatus,
        cr.CR_Desc AS CommitteeRole
    FROM eventcommittee ec
    JOIN event e ON ec.EventID = e.EventID
    LEFT JOIN committeerole cr ON ec.CR_ID = cr.CR_ID
    WHERE ec.StudentID = ?
    ORDER BY e.EventDateandTime DESC
";
$committee_stmt = $conn->prepare($committee_query);
$committee_stmt->bind_param("s", $student_id);
$committee_stmt->execute();
$committee_events = $committee_stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Event List</title>
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
        .event-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .event-table th, .event-table td {
            padding: 13px 15px;
            border-bottom: 1px solid #e2e2ea;
            text-align: left;
        }
        .event-table th {
            background-color: #ede7f6;
            color: #3a1d6e;
            font-weight: 600;
        }
        .event-table tr:last-child td {
            border-bottom: none;
        }
        .event-table tr:hover {
            background-color: #f3e9fc;
        }
        .view-btn {
            background: #5e3c99;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 7px 20px;
            font-size: 1em;
            cursor: pointer;
            display: inline-block;
            text-decoration: none;
        }
        .view-btn:hover {
            background: #9575cd;
            color: #fff;
        }
        .statusbadge {
            display: inline-block;
            min-width: 80px;
            padding: 4px 14px;
            border-radius: 4px;
            font-size: .96em;
            font-weight: 500;
            text-align: center;
        }
        .approved { 
            background: #d4edda; 
            color: #155724; 
        }
        .pending { 
            background: #fff3cd; 
            color: #856404; 
        }
        .rejected { 
            background: #f8d7da; 
            color: #721c24; 
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
        .footer {
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
                <a href="StudentDashboard.php" class="menuitem ">
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="StudEvent.php" class="menuitem active">
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
        <div class="content">
            <h1>Event</h1>
        </div>
        <div class="seccontent">
            <table class="event-table">
                <thead>
                    <tr>
                        <th>Event Name</th>
                        <th>Status</th>
                        <th>Committee Role</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($committee_events->num_rows > 0): ?>
                    <?php while ($row = $committee_events->fetch_assoc()): ?>
                        <?php
                            $status = strtolower($row['EventStatus']);
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
                        <tr>
                            <td><?= htmlspecialchars($row['EventTitle']); ?></td>
                            <td>
                                <span class="statusbadge <?= $statusClass ?>">
                                    <?= htmlspecialchars($row['EventStatus']); ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['CommitteeRole'] ?? '-'); ?></td>
                            <td>
                                <a href="ViewStudEvent.php?eventid=<?= urlencode($row['EventID']); ?>" class="view-btn">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align:center; color:#999;">No events found where you are a committee member.</td>
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