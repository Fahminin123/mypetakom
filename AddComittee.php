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
$event_id = isset($_GET['event_id']) ? $_GET['event_id'] : null;
if (!$event_id) {
    header("Location: Event.php");
    exit();
}

// Get all students not already a committee for this event
$existing_committee_query = "SELECT StudentID FROM committee WHERE EventID = ?";
$existing_stmt = $conn->prepare($existing_committee_query);
$existing_stmt->bind_param("s", $event_id);
$existing_stmt->execute();
$existing_committee_result = $existing_stmt->get_result();
$existing_student_ids = [];
while ($row = $existing_committee_result->fetch_assoc()) {
    $existing_student_ids[] = $row['StudentID'];
}

// Prepare SQL for students not already committee
if (!empty($existing_student_ids)) {
    $placeholders = implode(',', array_fill(0, count($existing_student_ids), '?'));
    $students_query = "SELECT StudentID, StudentName, StudentEmail FROM student WHERE StudentID NOT IN ($placeholders)";
    $stmt = $conn->prepare($students_query);
    $types = str_repeat('s', count($existing_student_ids));
    $stmt->bind_param($types, ...$existing_student_ids);
    $stmt->execute();
    $students_result = $stmt->get_result();
} else {
    $students_query = "SELECT StudentID, StudentName, StudentEmail FROM student";
    $students_result = $conn->query($students_query);
}

// Get all available committee roles
$roles_query = "SELECT * FROM committeerole";
$roles_result = $conn->query($roles_query);

// Handle add action
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id']) && isset($_POST['role_id'])) {
    $student_id = $_POST['student_id'];
    $role_id = $_POST['role_id'];

    // Double check the student isn't already a committee for this event
    $check_query = "SELECT * FROM committee WHERE EventID = ? AND StudentID = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ss", $event_id, $student_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $message = '<div class="message error">Student is already a committee member for this event.</div>';
    } else {
        $insert_query = "INSERT INTO committee (EventID, StudentID, CR_ID) VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("sss", $event_id, $student_id, $role_id);
        if ($insert_stmt->execute()) {
            header("Location: ViewEvent.php?id=" . $event_id);
            exit();
        } else {
            $message = '<div class="message error">Failed to add committee member.</div>';
        }
    }
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
            z-index: 1000;
            top: 0;
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
        .maincontent {
            margin: 0 auto;
            margin-top: 140px;
            max-width: 820px;
            padding: 30px 20px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #222;
            margin-bottom: 28px;
            text-align: center;
        }
        .studentlist {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .studentlist th, .studentlist td {
            padding: 12px 16px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }
        .studentlist th {
            background: #f7f7f7;
            font-weight: 600;
        }
        .addbutton {
            padding: 7px 14px;
            background: #1abc9c;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 0.98rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 7px;
            transition: background 0.2s;
        }
        .addbutton:hover {
            background: #16a085;
        }
        .roleselect {
            font-size: 0.97rem;
            padding: 5px 10px;
            border-radius: 5px;
            border: 1px solid #ddd;
            margin-right: 7px;
        }
        .backlink {
            display: inline-block;
            margin-top: 35px;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-size: 1rem;
        }
        .backlink:hover {
            background: #5a6268;
        }
        .message {
            padding: 13px 18px;
            border-radius: 5px;
            margin-bottom: 18px;
            font-size: 1rem;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .footer {
            background-color: #1f2d3d;
            color: white;
            text-align: center;
            padding: 15px 0;
            margin-top: 60px;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-left">
            <div class="Logo">
                <img src="Image/UMPSALogo.png" alt="LogoUMP">
                <img src="Image/PetakomLogo.png" alt="LogoPetakom">
            </div>
        </div>
        <div class="header-right">
            <a href="EventAdvisorProfile.php" class="backlink">
                <i class="fas fa-user-circle"></i> My Profile
            </a>
            <a href="logout.php" class="backlink"
               onclick="return confirm('Are you sure you want to log out?');">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>
    <div class="maincontent">
        <h1>Add Committee Member</h1>
        <?php if ($message) echo $message; ?>
        <table class="studentlist">
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role & Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($students_result->num_rows === 0): ?>
                <tr><td colspan="4" style="text-align:center;color:#888;">No students available.</td></tr>
            <?php else: ?>
                <?php while ($student = $students_result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($student['StudentID']) ?></td>
                        <td><?= htmlspecialchars($student['StudentName']) ?></td>
                        <td><?= htmlspecialchars($student['StudentEmail']) ?></td>
                        <td>
                            <form method="POST" style="display:inline-flex;align-items:center;">
                                <input type="hidden" name="student_id" value="<?= htmlspecialchars($student['StudentID']) ?>">
                                <select name="role_id" class="roleselect" required>
                                    <option value="">Role</option>
                                    <?php
                                    // Fetch roles again for every row because $roles_result is exhausted after first use
                                    $roles_inner_result = $conn->query($roles_query);
                                    while ($role = $roles_inner_result->fetch_assoc()): ?>
                                        <option value="<?= $role['CR_ID'] ?>"><?= htmlspecialchars($role['CR_Desc']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                                <button type="submit" class="addbutton"><i class="fas fa-plus"></i> Add</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <a href="ViewEvent.php?id=<?= urlencode($event_id) ?>" class="backlink">Back to Event</a>
    </div>
    <div class="footer">
        <footer>
            <p>&copy; 2025 MyPetakom</p>
        </footer>
    </div>
</body>
</html>