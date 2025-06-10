<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "mypetakom");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Restrict access to only logged-in event advisors
if (!isset($_SESSION['user_id']) || $_SESSION['type_user'] !== 'event_advisor') {
    header("Location: login.php");
    exit();
}

// Get staff data from database
$staff_id = $_SESSION['user_id'];
$query = "SELECT * FROM staff WHERE StaffID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    // Staff not found in database
    session_destroy();
    header("Location: login.php");
    exit();
}
$staff = $result->fetch_assoc();

// Handle Approve/Reject Actions
$action_message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && isset($_POST['claim_id'])) {
    $claim_id = $_POST['claim_id'];
    $action = $_POST['action'];
    if ($action === "approve") {
        $update = $conn->prepare("UPDATE meritclaim SET MeritClaimStatus = 'Approved' WHERE ClaimID = ? AND StaffID = ?");
        $update->bind_param("ss", $claim_id, $staff_id);
        if ($update->execute()) {
            $action_message = "<span style='color:green;'>Claim $claim_id has been approved.</span>";
        } else {
            $action_message = "<span style='color:red;'>Error approving claim: {$update->error}</span>";
        }
        $update->close();
    } elseif ($action === "reject") {
        $update = $conn->prepare("UPDATE meritclaim SET MeritClaimStatus = 'Rejected' WHERE ClaimID = ? AND StaffID = ?");
        $update->bind_param("ss", $claim_id, $staff_id);
        if ($update->execute()) {
            $action_message = "<span style='color:orange;'>Claim $claim_id has been rejected.</span>";
        } else {
            $action_message = "<span style='color:red;'>Error rejecting claim: {$update->error}</span>";
        }
        $update->close();
    }
    // Refresh the claims list immediately
    header("Location: MeritClaim.php?msg=" . urlencode(strip_tags($action_message)));
    exit();
}

// Fetch merit claims related to this event advisor (StaffID), but only for events with approved merit application
$claims = [];
$sql = "
SELECT 
    mc.*,
    ma.MeritApplicationStatus,
    e.EventTitle
FROM 
    meritclaim mc
    INNER JOIN meritapplication ma ON mc.EventID = ma.EventID
    INNER JOIN event e ON mc.EventID = e.EventID
WHERE 
    mc.StaffID = ?
    AND ma.MeritApplicationStatus = 'Approved'
ORDER BY mc.DateSubmitted DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $claims[] = $row;
}

// Handle messages from redirect
if (isset($_GET['msg'])) {
    $action_message = $_GET['msg'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Event Advisor Merit Claim</title>
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
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }
        .merit-claim-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .merit-claim-table th {
            background-color: #005b96;
            color: white;
            padding: 12px 15px;
            text-align: left;
        }
        .merit-claim-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        .merit-claim-table tr:hover {
            background-color: #f5f5f5;
        }
        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .status.pending {
            background-color: #FFF3CD;
            color: #856404;
        }
        .status.approved {
            background-color: #D4EDDA;
            color: #155724;
        }
        .status.rejected {
            background-color: #F8D7DA;
            color: #721C24;
        }
        .approve-btn, .reject-btn, .view-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            margin: 2px;
            transition: all 0.2s;
        }
        .approve-btn {
            background-color: #28a745;
            color: white;
        }
        .approve-btn:hover {
            background-color: #218838;
        }
        .reject-btn {
            background-color: #dc3545;
            color: white;
        }
        .reject-btn:hover {
            background-color: #c82333;
        }
        .view-btn {
            background-color: #17a2b8;
            color: white;
        }
        .view-btn:hover {
            background-color: #138496;
        }
        @media (max-width: 768px) {
            .merit-claim-table {
                display: block;
            }
            .merit-claim-table thead, 
            .merit-claim-table tbody, 
            .merit-claim-table th, 
            .merit-claim-table td, 
            .merit-claim-table tr { 
                display: block; 
            }
            .merit-claim-table thead tr { 
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            .merit-claim-table tr {
                border: 1px solid #ccc;
                margin-bottom: 10px;
            }
            .merit-claim-table td {
                border: none;
                position: relative;
                padding-left: 50%;
            }
            .merit-claim-table td:before {
                position: absolute;
                top: 12px;
                left: 15px;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                font-weight: bold;
            }
            .merit-claim-table td:nth-of-type(1):before { content: "No."; }
            .merit-claim-table td:nth-of-type(2):before { content: "Student ID"; }
            .merit-claim-table td:nth-of-type(3):before { content: "Event ID"; }
            .merit-claim-table td:nth-of-type(4):before { content: "Event Title"; }
            .merit-claim-table td:nth-of-type(5):before { content: "Date Submitted"; }
            .merit-claim-table td:nth-of-type(6):before { content: "Status"; }
            .merit-claim-table td:nth-of-type(7):before { content: "Proof Document"; }
            .merit-claim-table td:nth-of-type(8):before { content: "Actions"; }
        }
        .footer
        {
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
                <a href="EventAdvisorDashboard.php" class="menuitem ">
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
                <a href="Event.php" class="menuitem">
                    <span>Event</span>
                </a>
            </li>
            <li>
                <a href="MeritClaim.php" class="menuitem active">
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
            <h1>Merit Claim</h1>
        </div>
        <div class="seccontent">
            <?php if ($action_message): ?>
                <div style="margin-bottom:18px;"><?= $action_message ?></div>
            <?php endif; ?>
            <div class="table-container">
                <table class="merit-claim-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Student ID</th>
                            <th>Event ID</th>
                            <th>Event Title</th>
                            <th>Date Submitted</th>
                            <th>Status</th>
                            <th>Proof Document</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($claims)): ?>
                            <tr><td colspan="8" style="text-align:center;">No merit claims found.</td></tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($claims as $claim): ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td><?= htmlspecialchars($claim['StudentID']) ?></td>
                                    <td><?= htmlspecialchars($claim['EventID']) ?></td>
                                    <td><?= htmlspecialchars($claim['EventTitle']) ?></td>
                                    <td><?= htmlspecialchars($claim['DateSubmitted']) ?></td>
                                    <td>
                                        <span class="status <?= strtolower($claim['MeritClaimStatus']) ?>">
                                            <?= htmlspecialchars($claim['MeritClaimStatus']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($claim['ProofDocument'])): ?>
                                            <form method="post" action="viewDocument.php" target="_blank" style="display:inline;">
                                                <input type="hidden" name="claim_id" value="<?= htmlspecialchars($claim['ClaimID']) ?>">
                                                <button type="submit" class="view-btn">View Document</button>
                                            </form>
                                        <?php else: ?>
                                            No document
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($claim['MeritClaimStatus'] !== 'Approved' && $claim['MeritClaimStatus'] !== 'Rejected'): ?>
                                            <form method="post" action="" style="display:inline;">
                                                <input type="hidden" name="claim_id" value="<?= htmlspecialchars($claim['ClaimID']) ?>">
                                                <button type="submit" name="action" value="approve" class="approve-btn"><i class="fas fa-check"></i> Approve</button>
                                            </form>
                                            <form method="post" action="" style="display:inline;">
                                                <input type="hidden" name="claim_id" value="<?= htmlspecialchars($claim['ClaimID']) ?>">
                                                <button type="submit" name="action" value="reject" class="reject-btn"><i class="fas fa-times"></i> Reject</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color:gray;">No action</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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