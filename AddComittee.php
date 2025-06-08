<?php
session_start();
$conn = new mysqli("localhost", "root", "", "mypetakom");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$event_id = isset($_GET['event_id']) ? $_GET['event_id'] : null;
if (!$event_id) {
    header("Location: Event.php");
    exit();
}



// Get all available main roles from committeerole table that are not assigned in this event
$assigned_roles_query = "SELECT CR_ID FROM eventcommittee WHERE EventID = ? AND CR_ID IS NOT NULL AND CR_ID != ''";
$assigned_roles_stmt = $conn->prepare($assigned_roles_query);
$assigned_roles_stmt->bind_param("s", $event_id);
$assigned_roles_stmt->execute();
$assigned_roles_result = $assigned_roles_stmt->get_result();
$assigned_cr_ids = [];
while ($row = $assigned_roles_result->fetch_assoc()) {
    $assigned_cr_ids[] = $row['CR_ID'];
}

$roles_query = "SELECT CR_ID, CR_Desc FROM committeerole WHERE CR_Desc IN ('President', 'Vice President', 'Treasurer', 'Secretary')";
$roles_result = $conn->query($roles_query);
$role_options = [];
while ($row = $roles_result->fetch_assoc()) {
    if (!in_array($row['CR_ID'], $assigned_cr_ids)) {
        $role_options[$row['CR_ID']] = $row['CR_Desc'];
    }
}

// Handle add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_committee'])) {
    $student_id = $_POST['student_id'];
    $role_id = $_POST['role_id'];
    // Prevent duplicates (student and role for this event)
    $check = $conn->prepare("SELECT * FROM eventcommittee WHERE EventID=? AND (StudentID=? OR CR_ID=?)");
    $check->bind_param("sss", $event_id, $student_id, $role_id);
    $check->execute();
    $checkres = $check->get_result();
    if ($checkres->num_rows == 0) {
        $insert = $conn->prepare("INSERT INTO eventcommittee (EventID, StudentID, CR_ID) VALUES (?, ?, ?)");
        $insert->bind_param("sss", $event_id, $student_id, $role_id);
        $insert->execute();
    }
    header("Location: AddCommittee.php?event_id=$event_id");
    exit();
}

//Get current committee members for this event
$committee_query = "SELECT ec.CommitteeID, s.StudentName, cr.CR_Desc
    FROM eventcommittee ec
    JOIN student s ON ec.StudentID = s.StudentID
    LEFT JOIN committeerole cr ON ec.CR_ID = cr.CR_ID
    WHERE ec.EventID = ?";
$committee_stmt = $conn->prepare($committee_query);
$committee_stmt->bind_param("s", $event_id);
$committee_stmt->execute();
$committee_result = $committee_stmt->get_result();
$committee_members = [];
while ($row = $committee_result->fetch_assoc()) {
    $committee_members[] = $row;
}

//Get students not in committee for this event
$existing_student_ids_query = "SELECT StudentID FROM eventcommittee WHERE EventID = ?";
$existing_stmt = $conn->prepare($existing_student_ids_query);
$existing_stmt->bind_param("s", $event_id);
$existing_stmt->execute();
$existing_result = $existing_stmt->get_result();
$existing_student_ids = [];
while ($row = $existing_result->fetch_assoc()) {
    $existing_student_ids[] = $row['StudentID'];
}
if ($existing_student_ids) {
    $placeholders = implode(',', array_fill(0, count($existing_student_ids), '?'));
    $students_query = "SELECT StudentID, StudentName, StudentEmail FROM student WHERE StudentID NOT IN ($placeholders)";
    $students_stmt = $conn->prepare($students_query);
    $types = str_repeat('s', count($existing_student_ids));
    $students_stmt->bind_param($types, ...$existing_student_ids);
    $students_stmt->execute();
    $students_result = $students_stmt->get_result();
} else {
    $students_query = "SELECT StudentID, StudentName, StudentEmail FROM student";
    $students_result = $conn->query($students_query);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Committee Member</title>
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
            background-color: rgb(16, 8, 54);
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
        .studentlist {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            margin-bottom: 30px;
        }
        .studentlist th,
        .studentlist td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .studentlist th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        .studentlist tr:last-child td {
            border-bottom: none;
        }
        .studentlist tr:hover {
            background-color: #f5f5f5;
        }
        .addbutton {
            padding: 6px 13px;
            background-color: #1abc9c;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 7px;
            transition: background-color 0.2s;
        }
        .addbutton:hover {
            background-color: #168d75;
        }
        .backbutton {
            display: inline-block;
            margin-bottom: 20px;
            padding: 8px 15px;
            background-color: #1f2d3d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
        }
        .footer {
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
            <h1>Add Committee Member</h1>
            <a href="ViewEvent.php?id=<?= htmlspecialchars($event_id) ?>" class="backbutton">
                <i class="fas fa-arrow-left"></i> Back to Event
            </a>
        </div>
        <div class="seccontent">
            <?php if (empty($role_options)): ?>
                <div class="noroles" style="color:#b30000; text-align:center; padding:20px;">All main roles have been assigned for this event.</div>
            <?php elseif ($students_result->num_rows == 0): ?>
                <div class="noroles" style="color:#b30000; text-align:center; padding:20px;">No students available to add.</div>
            <?php else: ?>
                <form method="POST" action="AddCommittee.php?event_id=<?= htmlspecialchars($event_id) ?>">
                    <table class="studentlist">
                        <thead>
                            <tr>
                                <th>Select</th>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($student = $students_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <input type="radio" name="student_id" value="<?= htmlspecialchars($student['StudentID']) ?>" required>
                                    </td>
                                    <td><?= htmlspecialchars($student['StudentID']) ?></td>
                                    <td><?= htmlspecialchars($student['StudentName']) ?></td>
                                    <td><?= htmlspecialchars($student['StudentEmail']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <div style="margin: 20px 0;">
                        <label for="role_id"><b>Committee Role:</b></label>
                        <select name="role_id" id="role_id" required class="roledropdown" style="padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; width: 250px;">
                            <option value="">-- Select role --</option>
                            <?php foreach ($role_options as $cr_id => $cr_desc): ?>
                                <option value="<?= htmlspecialchars($cr_id) ?>"><?= htmlspecialchars($cr_desc) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="add_committee" class="addbutton"><i class="fas fa-plus"></i> Add Committee Member</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer">
        <footer>
            <center><p>&copy; 2025 MyPetakom</p></center>
        </footer>
    </div>
</body>
</html>
