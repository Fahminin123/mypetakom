<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "mypetakom");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Restrict access to only logged-in admins
if (!isset($_SESSION['user_id']) || $_SESSION['type_user'] !== 'coordinator') {
    header("Location: Login.php");
    exit();
}

// Get admin data from database
$admin_id = $_SESSION['user_id'];
$query = "SELECT * FROM staff WHERE StaffID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    session_destroy();
    header("Location: Login.php");
    exit();
}

$staff = $result->fetch_assoc();

// Feedback message (stored in session for redirect-based flash message)
if (!isset($_SESSION['message'])) $_SESSION['message'] = "";

// Handle deletion
if (isset($_GET['delete_student'])) {
    $delID = $_GET['delete_student'];
    $sql = "DELETE FROM student WHERE StudentID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $delID);
    if ($stmt->execute()) {
        $_SESSION['message'] = "<span class='alert-success'>Student deleted successfully!</span>";
    } else {
        $_SESSION['message'] = "<span class='alert-error'>Error deleting student: {$stmt->error}</span>";
    }
    header("Location: ManageUser.php");
    exit();
}
if (isset($_GET['delete_staff'])) {
    $delID = $_GET['delete_staff'];
    $sql = "DELETE FROM staff WHERE StaffID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $delID);
    if ($stmt->execute()) {
        $_SESSION['message'] = "<span class='alert-success'>Event advisor deleted successfully!</span>";
    } else {
        $_SESSION['message'] = "<span class='alert-error'>Error deleting advisor: {$stmt->error}</span>";
    }
    header("Location: ManageUser.php");
    exit();
}

// Handle edit (Student)
if (isset($_POST['edit_student_submit'])) {
    $sid = $_POST['edit_student_id'];
    $sname = $_POST['edit_student_name'];
    $scontact = $_POST['edit_student_contact'];
    $semail = $_POST['edit_student_email'];
    $sql = "UPDATE student SET StudentName=?, StudentContact=?, StudentEmail=? WHERE StudentID=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $sname, $scontact, $semail, $sid);
    if ($stmt->execute()) {
        $_SESSION['message'] = "<span class='alert-success'>Student updated successfully!</span>";
    } else {
        $_SESSION['message'] = "<span class='alert-error'>Error updating student: {$stmt->error}</span>";
    }
    header("Location: ManageUser.php");
    exit();
}

// Handle edit (Staff/Event Advisor)
if (isset($_POST['edit_staff_submit'])) {
    $sid = $_POST['edit_staff_id'];
    $sname = $_POST['edit_staff_name'];
    $scontact = $_POST['edit_staff_contact'];
    $semail = $_POST['edit_staff_email'];
    $position = $_POST['edit_staff_position'];
    $sql = "UPDATE staff SET StaffName=?, StaffContact=?, StaffEmail=?, Position=? WHERE StaffID=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $sname, $scontact, $semail, $position, $sid);
    if ($stmt->execute()) {
        $_SESSION['message'] = "<span class='alert-success'>Event advisor updated successfully!</span>";
    } else {
        $_SESSION['message'] = "<span class='alert-error'>Error updating advisor: {$stmt->error}</span>";
    }
    header("Location: ManageUser.php");
    exit();
}

// Handle add new user (student or event advisor)
if (isset($_POST['add_user_submit'])) {
    $usertype = $_POST['add_user_type'];
    if ($usertype == 'student') {
        $sid = $_POST['add_student_id'];
        $sname = $_POST['add_student_name'];
        $scontact = $_POST['add_student_contact'];
        $semail = $_POST['add_student_email'];
        $check = $conn->prepare("SELECT * FROM student WHERE StudentID=?");
        $check->bind_param("s", $sid);
        $check->execute();
        $res = $check->get_result();
        if ($res->num_rows > 0) {
            $_SESSION['message'] = "<span class='alert-error'>Student ID already exists!</span>";
        } else {
            $sql = "INSERT INTO student (StudentID, StudentName, StudentContact, StudentEmail) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $sid, $sname, $scontact, $semail);
            if ($stmt->execute()) {
                $_SESSION['message'] = "<span class='alert-success'>New student added successfully!</span>";
            } else {
                $_SESSION['message'] = "<span class='alert-error'>Error adding student: {$stmt->error}</span>";
            }
        }
    } else if ($usertype == 'eventadvisor') {
        $sid = $_POST['add_staff_id'];
        $sname = $_POST['add_staff_name'];
        $scontact = $_POST['add_staff_contact'];
        $semail = $_POST['add_staff_email'];
        $spass = $_POST['add_staff_password'];
        $position = "EventAdvisor";
        $check = $conn->prepare("SELECT * FROM staff WHERE StaffID=?");
        $check->bind_param("s", $sid);
        $check->execute();
        $res = $check->get_result();
        if ($res->num_rows > 0) {
            $_SESSION['message'] = "<span class='alert-error'>Staff ID already exists!</span>";
        } else {
            $sql = "INSERT INTO staff (StaffID, StaffName, StaffContact, StaffEmail, StaffPassword, Position) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssss", $sid, $sname, $scontact, $semail, $spass, $position);
            if ($stmt->execute()) {
                $_SESSION['message'] = "<span class='alert-success'>New event advisor added successfully!</span>";
            } else {
                $_SESSION['message'] = "<span class='alert-error'>Error adding event advisor: {$stmt->error}</span>";
            }
        }
    }
    header("Location: ManageUser.php");
    exit();
}

// Fetch all students
$students = [];
$result = $conn->query("SELECT * FROM student");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
}

// Fetch staff with EventAdvisor position
$event_advisors = [];
$result2 = $conn->query("SELECT * FROM staff WHERE Position = 'EventAdvisor'");
if ($result2) {
    while ($row = $result2->fetch_assoc()) {
        $event_advisors[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage User</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ... (same CSS as before) ... */
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
            background-color:rgb(222, 116, 24); 
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
            background-color: #d35400; 
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
            background-color: #a04000;
        }
        .menuitem.active {
            background-color: #e67e22;
            font-weight: 500;
        }
        .togglebutton {
            background-color: #e67e22;
            color: white;
            border: 1px solid rgba(230, 126, 34, 0.3);
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .togglebutton:hover {
            background-color: #d35400;
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
            background-color: rgba(52, 152, 219, 0.3);
        }
        .maincontent {
            margin-left: 200px;
            margin-top: 120px;
            padding: 40px;
            flex: 1;
            box-sizing: border-box;
            gap: 40px;
            transition: margin-left 0.3s ease;
            justify-content: space-between;
        }
        .maincontent.expanded {
            margin-left: 0;
        }
        .content {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .seccontent {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            margin-top: 30px;
        }
        h2 {
            margin-top: 40px;
        }
        .btn-add {
            background-color: #008CCF;
            color: white;
            font-size: 1.2em;
            padding: 12px 30px;
            margin: 25px 0 0 0;
            display: inline-block;
            border: none;
            border-radius: 5px;
            font-weight: 500;
            cursor: pointer;
        }
        .btn-add:hover {
            background-color: #005f8f;
        }
        table, th, td {
            border:1px solid black;
            border-collapse: collapse;
        }
        th, td {
            padding: 8px 12px;
        }
        table {
            width: 100%;
            background: #fafafa;
            margin-bottom: 0;
        }
        th {
            background: #8ecae6;
            color: #222;
        }
        tr:nth-child(even) { background: #f1f8fc; }
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-edit {
            background-color: #3498db;
            color: white;
        }
        .btn-edit:hover {
            background-color: #2980b9;
        }
        .btn-delete {
            background-color: #e74c3c;
            color: white;
        }
        .btn-delete:hover {
            background-color: #c0392b;
        }
        .alert-success {
            color: #276f42;
            background: #e0f7e9;
            border: 1px solid #a4e4b5;
            padding: 8px 18px;
            border-radius: 5px;
            display: inline-block;
            margin: 12px 0 16px 0;
        }
        .alert-error {
            color: #842029;
            background: #f8d7da;
            border: 1px solid #f5c2c7;
            padding: 8px 18px;
            border-radius: 5px;
            display: inline-block;
            margin: 12px 0 16px 0;
        }
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 100;
            left: 0; top: 0; width: 100%; height: 100%;
            overflow: auto;
            background: rgba(0,0,0,0.4);
        }
        .modal-content {
            background: #fff;
            margin: 6% auto;
            padding: 22px;
            border: 1px solid #888;
            width: 390px;
            border-radius: 8px;
        }
        .close {
            color: #aaa; float: right; font-size: 28px; font-weight: bold;
        }
        .close:hover, .close:focus { color: black; text-decoration: none; cursor: pointer; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input, .form-group select { width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #bbb;}
        .form-buttons { text-align: right; }
        .form-buttons button { margin-left: 10px; }
        .footer {
            background-color: #e67e22;
            color: white;
            padding: 15px 0;
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
            <a href="AdminProfile.php" class="profilebutton">
                <i class="fas fa-user-circle"></i> My Profile
            </a>
            <a href="logout.php" class="logoutbutton" onclick="return confirm('Are you sure you want to log out?');">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>

    <nav class="sidebar" id="sidebar">
        <h2 class="sidebartitle">Admin</h2>
        <ul class="menuitems">
            <li>
                <a href="AdminDashboard.php" class="menuitem">
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="ManageUser.php" class="menuitem active">
                    <span>Manage User</span>
                </a>
            </li>
            <li>
                <a href="MeritApplicationApproval.php" class="menuitem">
                    <span>Merit Application Approval</span>
                </a>
            </li>
            <li>
                <a href="MembershipApproval.php" class="menuitem">
                    <span>Membership Approval</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="maincontent" id="maincontent">
        <div class="content">
            <h1>Manage User</h1>
        </div>
        <div class="seccontent">

        <!-- Flash message below Add User Button -->
        <?php
            if (!empty($_SESSION['message'])) {
                echo "<div id='flash-message'>".$_SESSION['message']."</div>";
                $_SESSION['message'] = "";
            }
        ?>

        <!-- Student Table -->
        <h2>Student List</h2>
        <table>
            <tr>
                <th>Student ID</th>
                <th>Student Name</th>
                <th>Student Contact</th>
                <th>Student Email</th>
                <th>Action</th>
            </tr>
            <?php if (empty($students)): ?>
                <tr><td colspan="5" style="text-align:center;color:#888;">No students found.</td></tr>
            <?php else: ?>
                <?php foreach ($students as $stud): ?>
                <tr>
                    <td><?= htmlspecialchars($stud['StudentID']) ?></td>
                    <td><?= htmlspecialchars($stud['StudentName']) ?></td>
                    <td><?= htmlspecialchars($stud['StudentContact']) ?></td>
                    <td><?= htmlspecialchars($stud['StudentEmail']) ?></td>
                    <td>
                        <button class="btn btn-edit" onclick="openEditStudentModal('<?=htmlspecialchars($stud['StudentID'])?>','<?=htmlspecialchars($stud['StudentName'])?>','<?=htmlspecialchars($stud['StudentContact'])?>','<?=htmlspecialchars($stud['StudentEmail'])?>')">Edit</button>
                        <a href="?delete_student=<?= urlencode($stud['StudentID']) ?>" class="btn btn-delete" onclick="return confirm('Delete this student?');">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>

        <!-- Event Advisor Staff Table -->
        <h2>Event Advisor List</h2>
        <table>
            <tr>
                <th>Staff ID</th>
                <th>Staff Name</th>
                <th>Staff Contact</th>
                <th>Staff Email</th>
                <th>Position</th>
                <th>Action</th>
            </tr>
            <?php if (empty($event_advisors)): ?>
                <tr><td colspan="6" style="text-align:center;color:#888;">No event advisors found.</td></tr>
            <?php else: ?>
                <?php foreach ($event_advisors as $stf): ?>
                <tr>
                    <td><?= htmlspecialchars($stf['StaffID']) ?></td>
                    <td><?= htmlspecialchars($stf['StaffName']) ?></td>
                    <td><?= htmlspecialchars($stf['StaffContact']) ?></td>
                    <td><?= htmlspecialchars($stf['StaffEmail']) ?></td>
                    <td><?= htmlspecialchars($stf['Position']) ?></td>
                    <td>
                        <button class="btn btn-edit" onclick="openEditStaffModal('<?=htmlspecialchars($stf['StaffID'])?>','<?=htmlspecialchars($stf['StaffName'])?>','<?=htmlspecialchars($stf['StaffContact'])?>','<?=htmlspecialchars($stf['StaffEmail'])?>','<?=htmlspecialchars($stf['Position'])?>')">Edit</button>
                        <a href="?delete_staff=<?= urlencode($stf['StaffID']) ?>" class="btn btn-delete" onclick="return confirm('Delete this event advisor?');">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>

        <!-- Add User Button (Below Tables) -->
        <button class="btn-add" onclick="document.getElementById('addUserModal').style.display='block'">Add New User</button>

        </div>
    </div>

    <!-- (Modals and scripts remain unchanged) -->
    <!-- ... Modal definitions and JS scripts from your code ... -->
    <!-- (Paste the modals and JS here as in your original, unchanged) -->

    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('addUserModal').style.display='none'">&times;</span>
            <h3>Add New User</h3>
            <form method="post" id="addUserForm">
                <div class="form-group">
                    <label for="add_user_type">User Type:</label>
                    <select name="add_user_type" id="add_user_type" required onchange="toggleAddUserFields()">
                        <option value="">Select Type</option>
                        <option value="student">Student</option>
                        <option value="eventadvisor">Event Advisor</option>
                    </select>
                </div>
                <div id="studentFields" style="display:none;">
                    <div class="form-group">
                        <label for="add_student_id">Student ID:</label>
                        <input type="text" name="add_student_id" id="add_student_id">
                    </div>
                    <div class="form-group">
                        <label for="add_student_name">Name:</label>
                        <input type="text" name="add_student_name" id="add_student_name">
                    </div>
                    <div class="form-group">
                        <label for="add_student_contact">Contact:</label>
                        <input type="text" name="add_student_contact" id="add_student_contact">
                    </div>
                    <div class="form-group">
                        <label for="add_student_email">Email:</label>
                        <input type="email" name="add_student_email" id="add_student_email">
                    </div>
                </div>
                <div id="staffFields" style="display:none;">
                    <div class="form-group">
                        <label for="add_staff_id">Staff ID:</label>
                        <input type="text" name="add_staff_id" id="add_staff_id">
                    </div>
                    <div class="form-group">
                        <label for="add_staff_name">Name:</label>
                        <input type="text" name="add_staff_name" id="add_staff_name">
                    </div>
                    <div class="form-group">
                        <label for="add_staff_contact">Contact:</label>
                        <input type="text" name="add_staff_contact" id="add_staff_contact">
                    </div>
                    <div class="form-group">
                        <label for="add_staff_email">Email:</label>
                        <input type="email" name="add_staff_email" id="add_staff_email">
                    </div>
                    <div class="form-group">
                        <label for="add_staff_password">Password:</label>
                        <input type="text" name="add_staff_password" id="add_staff_password">
                    </div>
                </div>
                <div class="form-buttons">
                    <button type="button" onclick="document.getElementById('addUserModal').style.display='none'">Cancel</button>
                    <button type="submit" name="add_user_submit">Add</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Student Modal -->
    <div id="editStudentModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditStudentModal()">&times;</span>
            <h3>Edit Student</h3>
            <form method="post">
                <div class="form-group">
                    <label for="edit_student_id">Student ID (cannot edit):</label>
                    <input type="text" name="edit_student_id" id="edit_student_id" readonly style="background:#eee;">
                </div>
                <div class="form-group">
                    <label for="edit_student_name">Name:</label>
                    <input type="text" name="edit_student_name" id="edit_student_name" required>
                </div>
                <div class="form-group">
                    <label for="edit_student_contact">Contact:</label>
                    <input type="text" name="edit_student_contact" id="edit_student_contact" required>
                </div>
                <div class="form-group">
                    <label for="edit_student_email">Email:</label>
                    <input type="email" name="edit_student_email" id="edit_student_email" required>
                </div>
                <div class="form-buttons">
                    <button type="button" onclick="closeEditStudentModal()">Cancel</button>
                    <button type="submit" name="edit_student_submit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Staff Modal -->
    <div id="editStaffModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditStaffModal()">&times;</span>
            <h3>Edit Event Advisor</h3>
            <form method="post">
                <div class="form-group">
                    <label for="edit_staff_id">Staff ID (cannot edit):</label>
                    <input type="text" name="edit_staff_id" id="edit_staff_id" readonly style="background:#eee;">
                </div>
                <div class="form-group">
                    <label for="edit_staff_name">Name:</label>
                    <input type="text" name="edit_staff_name" id="edit_staff_name" required>
                </div>
                <div class="form-group">
                    <label for="edit_staff_contact">Contact:</label>
                    <input type="text" name="edit_staff_contact" id="edit_staff_contact" required>
                </div>
                <div class="form-group">
                    <label for="edit_staff_email">Email:</label>
                    <input type="email" name="edit_staff_email" id="edit_staff_email" required>
                </div>
                <div class="form-group">
                    <label for="edit_staff_position">Position:</label>
                    <input type="text" name="edit_staff_position" id="edit_staff_position" required readonly style="background:#eee;">
                </div>
                <div class="form-buttons">
                    <button type="button" onclick="closeEditStaffModal()">Cancel</button>
                    <button type="submit" name="edit_staff_submit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleAddUserFields() {
            let type = document.getElementById('add_user_type').value;
            document.getElementById('studentFields').style.display = type === 'student' ? 'block' : 'none';
            document.getElementById('staffFields').style.display = type === 'eventadvisor' ? 'block' : 'none';
        }
        // Student modal
        function openEditStudentModal(id, name, contact, email) {
            document.getElementById('edit_student_id').value = id;
            document.getElementById('edit_student_name').value = name;
            document.getElementById('edit_student_contact').value = contact;
            document.getElementById('edit_student_email').value = email;
            document.getElementById('editStudentModal').style.display = "block";
        }
        function closeEditStudentModal() {
            document.getElementById('editStudentModal').style.display = "none";
        }
        // Staff modal
        function openEditStaffModal(id, name, contact, email, position) {
            document.getElementById('edit_staff_id').value = id;
            document.getElementById('edit_staff_name').value = name;
            document.getElementById('edit_staff_contact').value = contact;
            document.getElementById('edit_staff_email').value = email;
            document.getElementById('edit_staff_position').value = position;
            document.getElementById('editStaffModal').style.display = "block";
        }
        function closeEditStaffModal() {
            document.getElementById('editStaffModal').style.display = "none";
        }
        // Close modal on outside click
        window.onclick = function(event) {
            if (event.target == document.getElementById('editStudentModal')) closeEditStudentModal();
            if (event.target == document.getElementById('editStaffModal')) closeEditStaffModal();
            if (event.target == document.getElementById('addUserModal')) document.getElementById('addUserModal').style.display = "none";
        }
        // Sidebar toggle
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