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
$stmt->bind_param("s", $admin_id); // Fix applied here
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    // Admin not found in database
    session_destroy();
    header("Location: Login.php");
    exit();
}

$staff = $result->fetch_assoc();

// Fetch all merit applications that are pending
$merit_query = "SELECT ma.MeritApplicationID, ma.EventID, ma.MeritApplicationStatus, e.EventTitle, e.EventDateandTime, e.EventVenue
                FROM meritapplication ma
                JOIN event e ON ma.EventID = e.EventID
                WHERE ma.MeritApplicationStatus = 'Pending'";
$merit_result = $conn->query($merit_query);

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
            background-color: #a04000; /* Even darker orange */
        }

        .menuitem.active {
            background-color: #e67e22; /* Matching header orange */
            font-weight: 500;
        }

        .togglebutton {
            background-color: #e67e22; /* Orange */
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
            background-color: #d35400; /* Darker orange */
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

        /* Structured merit table style */
        .merit-table-container {
            width: 100%;
            margin: 0 auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.07);
            padding: 24px 0 0 0;
        }
        .merit-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
        }
        .merit-table th, .merit-table td {
            padding: 14px 12px;
            text-align: left;
        }
        .merit-table th {
            background-color: #faf3ea;
            font-weight: 600;
            color: #c96a14;
            border-bottom: 2px solid #f5e1c6;
        }
        .merit-table td {
            border-bottom: 1px solid #f5e1c6;
            vertical-align: middle;
        }
        .merit-table tr:last-child td {
            border-bottom: none;
        }
        .merit-table tr:hover {
            background-color: #fffaee;
        }
        
        .merit-action-btn {
            background: #e67e22;
            color: white;
            padding: 8px 20px;
            border-radius: 5px;
            text-decoration: none;
            border: none;
            font-size: 0.99rem;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: background 0.2s;
        }
        .merit-action-btn:hover {
            background: #d35400;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 20px 0;
        }

        .empty-state i {
            font-size: 48px;
            color: #28a745;
            margin-bottom: 15px;
        }

        .empty-state h3 {
            color: #343a40;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #6c757d;
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
                <a href="ManageUser.php" class="menuitem">
                    <span>Manage User</span>
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
            <h1>Merit Application Approval</h1>
            
        </div>

        <div class="seccontent">
            <div class="merit-table-container">
            <?php if ($merit_result->num_rows > 0): ?>
                <table class="merit-table">
                    <thead>
                        <tr>
                            <th>Event Title</th>
                            <th>Venue</th>
                            <th>Date & Time</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $merit_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['EventTitle']) ?></td>
                            <td><?= htmlspecialchars($row['EventVenue']) ?></td>
                            <td><?= date('j F Y, g:i A', strtotime($row['EventDateandTime'])) ?></td>
                            <td>
                                <a href="ViewMeritApplication.php?merit_id=<?= urlencode($row['MeritApplicationID']) ?>" class="merit-action-btn">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <h3>No Pending Applications</h3>
                    <p>There are currently no merit applications awaiting review.</p>
                </div>
            <?php endif; ?>
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