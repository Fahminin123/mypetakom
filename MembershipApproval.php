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
    // Admin not found in database
    session_destroy();
    header("Location: Login.php");
    exit();
}

$staff = $result->fetch_assoc();

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['application_id'])) {
    $applicationId = $_POST['application_id'];
    $action = $_POST['action'];
    $newStatus = ($action === 'approve') ? 'Approved' : 'Rejected';

    $updateQuery = "UPDATE memberapplication SET MemStatus = ?, StaffID = ? WHERE MemberApplicationID = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("sss", $newStatus, $admin_id, $applicationId);
    $updateStmt->execute();
    $updateStmt->close();
}

// Get all membership applications with student info (FIXED JOIN)
$applicationsQuery = "
    SELECT ma.MemberApplicationID, ma.MemStatus, ma.StudentCard, s.StudentName, s.StudentID, s.StudentEmail
    FROM memberapplication ma
    JOIN student s ON ma.StudentID = s.StudentID
    ORDER BY FIELD(ma.MemStatus, 'Pending', 'Approved', 'Rejected'), ma.MemberApplicationID ASC
";
$applicationsResult = $conn->query($applicationsQuery);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Membership Approval</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f5f5f5; font-family: 'Roboto', sans-serif; margin: 0; padding: 0; display: flex; flex-direction: column; min-height: 100vh;}
        .header { background-color:rgb(222, 116, 24); display: flex; justify-content: space-between; align-items: center; padding: 0 20px; position: fixed; width: 100%; box-sizing: border-box; height: 120px;}
        .header-left { display: flex; align-items: center; gap: 20px; padding: 0 35px;}
        .header-right { display: flex; align-items: center; gap: 20px; padding-right: 20px;}
        .Logo { display: flex; gap: 20px; align-items: center; padding: 0 60px;}
        .Logo img { height: 90px; width: auto;}
        .sidebar { width: 200px; background-color: #d35400; color: white; position: fixed; top: 120px; left: 0; bottom: 0; padding: 20px 0; box-sizing: border-box; transition: transform 0.3s ease;}
        .sidebar.collapsed { transform: translateX(-200px);}
        .sidebartitle { color: white; font-size: 1.4rem; margin-bottom: 20px; padding: 0 20px;}
        .menuitems { display: flex; flex-direction: column; gap: 8px; padding: 0; margin: 0; list-style: none;}
        .menuitem { background-color: rgba(255, 255, 255, 0.1); border-radius: 6px; padding: 14px 18px; color: white; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 12px;}
        .menuitems a { text-decoration: none; color: inherit;}
        .menuitem:hover { background-color: #a04000;}
        .menuitem.active { background-color: #e67e22; font-weight: 500;}
        .togglebutton { background-color: #e67e22; color: white; border: 1px solid rgba(230, 126, 34, 0.3); padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; gap: 8px;}
        .togglebutton:hover { background-color: #d35400;}
        .logoutbutton { background-color: rgba(255, 0, 0, 0.2); color: white; border: 1px solid rgba(255, 0, 0, 0.3); padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; gap: 8px; text-decoration: none;}
        .profilebutton { background-color: rgba(46, 204, 113, 0.2); color: white; border: 1px solid rgba(46, 204, 113, 0.3); padding: 8px 15px; border-radius: 4px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; gap: 8px; transition: all 0.2s; text-decoration: none;}
        .profilebutton:hover { background-color: rgba(52, 152, 219, 0.3);}
        .maincontent { margin-left: 200px; margin-top: 120px; padding: 40px; flex: 1; box-sizing: border-box; gap: 40px; transition: margin-left 0.3s ease; justify-content: space-between;}
        .maincontent.expanded { margin-left: 0;}
        .content { background-color: white; padding: 25px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 2px 15px rgba(0,0,0,0.05);}
        .content h1 { font-size: 1.5rem; margin: 0; color: black; font-weight: 600;}
        .seccontent { background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 15px rgba(0,0,0,0.05);}
        .table-container { overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; min-width: 700px; margin: 0 auto; background: #faf9f6;}
        th, td { padding: 10px 12px; border-bottom: 1px solid #ddd; text-align: left;}
        th { background: #e67e22; color: #fff;}
        tr.pending td { background: #fff9e7;}
        tr.approved td { background: #eafaf1;}
        tr.rejected td { background: #faeaea;}
        .action-btn { padding: 6px 18px; border: none; border-radius: 5px; color: #fff; font-size: 1rem; cursor: pointer; margin-right: 8px;}
        .approve-btn { background: #27ae60;}
        .approve-btn:hover { background: #219150;}
        .reject-btn { background: #c0392b;}
        .reject-btn:hover { background: #a93226;}
        .disabled-btn { background: #b2bec3; cursor: not-allowed;}
        .view-card-link { color: #2471A3; text-decoration: underline; cursor: pointer; }
        .footer { background-color: #e67e22; color: white; padding: 15px 0;}
        @media (max-width: 900px){
            .maincontent {padding: 8px;}
            table {font-size: 0.95rem;}
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
                 <li>
            <a href="ManageUser.php" class="menuitem">
                <span>Manage User</span>
    </a>
            </li>
          
            </li>
            <li>
                <a href="MeritApplicationApproval.php" class="menuitem">
                    <span>Merit Application Approval</span>
                </a>
            </li>
            <li>
                <a href="MembershipApproval.php" class="menuitem active">
                    <span>Membership Approval</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="maincontent" id="maincontent">
        <div class="content">
            <h1>Membership Approval</h1>
        </div>
        <div class="seccontent">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Student Name</th>
                            <th>Student ID</th>
                            <th>Email</th>
                            <th>Student Card</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        if ($applicationsResult && $applicationsResult->num_rows > 0):
                            while ($row = $applicationsResult->fetch_assoc()):
                                $statusClass = strtolower($row['MemStatus']);
                        ?>
                        <tr class="<?php echo $statusClass; ?>">
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($row['StudentName']); ?></td>
                            <td><?php echo htmlspecialchars($row['StudentID']); ?></td>
                            <td><?php echo htmlspecialchars($row['StudentEmail']); ?></td>
                            <td>
                                <?php if (!empty($row['StudentCard'])): ?>
                                    <a class="view-card-link" href="view_student_card.php?id=<?php echo urlencode($row['StudentID']); ?>" target="_blank">View</a>
                                <?php else: ?>
                                    No File
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($row['MemStatus']); ?>
                            </td>
                            <td>
                                <?php if (strtolower($row['MemStatus']) === 'pending'): ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="application_id" value="<?php echo htmlspecialchars($row['MemberApplicationID']); ?>">
                                    <button type="submit" name="action" value="approve" class="action-btn approve-btn" onclick="return confirm('Approve this application?');">Approve</button>
                                </form>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="application_id" value="<?php echo htmlspecialchars($row['MemberApplicationID']); ?>">
                                    <button type="submit" name="action" value="reject" class="action-btn reject-btn" onclick="return confirm('Reject this application?');">Reject</button>
                                </form>
                                <?php else: ?>
                                    <button class="action-btn disabled-btn" disabled>Done</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center;">No applications found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Student Card Viewer (opens student card in new tab) -->
    <!-- view_student_card.php: see note below -->

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