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

// Get merit ID from URL
$merit_id = isset($_GET['merit_id']) ? $_GET['merit_id'] : null;
if (!$merit_id) {
    header("Location: MeritApplicationApproval.php");
    exit();
}

// Fetch merit application and event
$ma_query = "SELECT ma.*, e.*, s.StaffName as AdvisorName
             FROM meritapplication ma
             JOIN event e ON ma.EventID = e.EventID
             LEFT JOIN staff s ON e.StaffID = s.StaffID
             WHERE ma.MeritApplicationID = ?";
$ma_stmt = $conn->prepare($ma_query);
$ma_stmt->bind_param("s", $merit_id);
$ma_stmt->execute();
$ma_result = $ma_stmt->get_result();
if ($ma_result->num_rows == 0) {
    header("Location: MeritApplicationApproval.php");
    exit();
}
$app = $ma_result->fetch_assoc();

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $new_status = $_POST['action'] === 'approve' ? 'Approved' : 'Rejected';
    $update_query = "UPDATE meritapplication SET MeritApplicationStatus = ?, StaffID = ? WHERE MeritApplicationID = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("sss", $new_status, $admin_id, $merit_id);
    $update_stmt->execute();
    header("Location: MeritApplicationApproval.php");
    exit();
}

// Fix: Build approval letter link based on only the filename stored in DB
$approvalLetterFile = $app['ApprovalLetter'];
$approvalLetterPath = '';
if (!empty($approvalLetterFile)) {
    // Make sure it's just the filename (no path), and the file exists
    $filePath = __DIR__ . '/uploads/' . $approvalLetterFile;
    if (file_exists($filePath)) {
        $approvalLetterPath = 'uploads/' . rawurlencode($approvalLetterFile);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Merit Application Approval</title>
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

        .event-detail-list {
            list-style: none;
            padding: 0;
            margin: 0 0 25px 0;
        }
        .event-detail-list li {
            margin-bottom: 12px;
        }
        .event-label {
            display: inline-block;
            font-weight: 600;
            min-width: 180px;
            color: #e67e22;
        }
        .statusbadge {
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 0.92rem;
            font-weight: 500;
            background: #fff3cd;
            color: #856404;
        }
        .approval-btns {
            margin-top: 18px;
            display: flex;
            gap: 16px;
        }
        .approve-btn, .reject-btn {
            padding: 9px 26px;
            border: none;
            border-radius: 4px;
            font-size: 1.02rem;
            color: #fff;
            cursor: pointer;
        }
        .approve-btn {
            background-color: #27ae60;
        }
        .reject-btn {
            background-color: #c0392b;
        }
        .approval-letter-link {
            color: #e67e22;
            text-decoration: underline;
            margin-left: 6px;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 18px;
            color: #e67e22;
            text-decoration: underline;
        }

        .footer {
            background-color: #e67e22; /* Orange */
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
            <a href="MeritApplicationApproval.php" class="menuitem active">
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
            <h1>Merit Application Detail</h1>
        </div>
        <div class="seccontent">
            <a href="MeritApplicationApproval.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Pending Applications</a>
            <ul class="event-detail-list">
                <li>
                    <span class="event-label">Event Title:</span>
                    <?= htmlspecialchars($app['EventTitle']) ?>
                </li>
                <li>
                    <span class="event-label">Event Venue:</span>
                    <?= htmlspecialchars($app['EventVenue']) ?>
                </li>
                <li>
                    <span class="event-label">Date & Time:</span>
                    <?= date('j F Y, g:i A', strtotime($app['EventDateandTime'])) ?>
                </li>
                <li>
                    <span class="event-label">Event Status:</span>
                    <?= htmlspecialchars($app['EventStatus']) ?>
                </li>
                <li>
                    <span class="event-label">Event Advisor:</span>
                    <?= htmlspecialchars($app['AdvisorName']) ?>
                </li>
                <li>
                    <span class="event-label">Geolocation:</span>
                    <?= htmlspecialchars($app['geolocation']) ?>
                </li>
                <li>
                    <span class="event-label">Approval Letter:</span>
                    <?php (!empty($app['ApprovalLetter'])) ?>
                        <a href="<?php echo $app['ApprovalLetter']; ?>" target="_blank">
                            View Approval Letter
                        </a>
                    <?php?>
                </li>
                <li>
                    <span class="event-label">Merit Application Status:</span>
                    <span class="statusbadge"><?= htmlspecialchars($app['MeritApplicationStatus']) ?></span>
                </li>
            </ul>
            <form method="POST" class="approval-btns" onsubmit="return confirm('Are you sure you want to proceed with this action?');">
                <button type="submit" name="action" value="approve" class="approve-btn">
                    <i class="fas fa-check"></i> Approve
                </button>
                <button type="submit" name="action" value="reject" class="reject-btn">
                    <i class="fas fa-times"></i> Reject
                </button>
            </form>
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