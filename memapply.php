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

// Generate new MemberApplicationID with "MA" prefix and 2-digit sequential number
function generateMemberApplicationID($conn) {
    $result = $conn->query("SELECT MemberApplicationID FROM memberapplication WHERE MemberApplicationID LIKE 'MA%' ORDER BY MemberApplicationID DESC LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        $lastId = intval(substr($row['MemberApplicationID'], 2));
        $newIdNum = $lastId + 1;
    } else {
        $newIdNum = 1;
    }
    return 'MA' . str_pad($newIdNum, 2, '0', STR_PAD_LEFT); // e.g., MA01, MA02
}

$student_id = $_SESSION['user_id'];

// Handle file upload and insert into memberapplication
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fileAttachment'])) {
    $fileTmpPath = $_FILES['fileAttachment']['tmp_name'];
    $fileSize = $_FILES['fileAttachment']['size'];

    if (is_uploaded_file($fileTmpPath) && $fileSize > 0) {
        $studentCardData = file_get_contents($fileTmpPath);

        // Only allow one application per student
        $checkQuery = "SELECT 1 FROM memberapplication WHERE StudentID = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("s", $student_id);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows === 0) {
            $memStatusInsert = "Pending";
            $staffId = "P100";
            $newMemberApplicationID = generateMemberApplicationID($conn);
            $insertQuery = "INSERT INTO memberapplication (MemberApplicationID, StaffID, StudentID, MemStatus, StudentCard) VALUES (?, ?, ?, ?, ?)";
            $insertStmt = $conn->prepare($insertQuery);
            if ($insertStmt) {
                $insertStmt->send_long_data(4, $studentCardData);
                $insertStmt->bind_param("sssss", $newMemberApplicationID, $staffId, $student_id, $memStatusInsert, $studentCardData);
                $insertStmt->execute();
                $insertStmt->close();
            }
        }
        $checkStmt->close();
    }
    header("Location: membership.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Apply PETAKOM Membership</title>
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
            z-index: 99;
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
            z-index: 98;
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
            margin-top: 120px;
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
            text-align: center;
            max-width: 500px;
            margin: 30px auto;
        }
        .info-row {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .form-group {
            margin-bottom: 15px;
            width: 100%;
        }
        label {
            font-weight: 500;
            margin-bottom: 8px;
            display: block;
            text-align: left;
        }
        .file-input {
            width: 100%;
            margin-bottom: 8px;
        }
        .apply-btn {
            background-color: #5e3c99;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 12px 25px;
            font-size: 1.1rem;
            cursor: pointer;
            margin-top: 18px;
            transition: background 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        .apply-btn:hover {
            background-color: #9575cd;
        }
        .footer {
            background-color: #3a1d6e; 
            color: white;
            position: fixed;
            bottom: 0;
            width: 100%;
            left: 0;
            z-index: 100;
        }
        @media (max-width:700px) {
            .maincontent { margin-left: 0; padding: 10px; }
            .sidebar { left: -200px; }
            .sidebar.collapsed { transform: translateX(0); }
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
                <a href="StudEvent.php" class="menuitem">
                    <span>Event</span>
                </a>
            </li>
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
            <h1>Apply for PETAKOM Membership</h1>
        </div>
        <div class="seccontent">
            <form method="post" enctype="multipart/form-data">
                <div class="info-row">
                    <div class="form-group">
                        <label for="fileAttachment">Upload your Student Card:</label>
                        <input type="file" id="fileAttachment" name="fileAttachment" class="file-input" required>
                    </div>
                </div>
                <button type="submit" class="apply-btn">Apply</button>
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