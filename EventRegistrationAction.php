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
    session_destroy();
    header("Location: login.php");
    exit();
}
$staff = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Event Registration Action</title>
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

        .footer
        {
            background-color: #1f2d3d;
            color: white;
        }

        /* Message styles */
        .message {
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            font-weight: 500;
            line-height: 1.6;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
            padding: 10px 15px;
            background-color: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
            font-size: 0.9rem;
        }

        .back-link:hover {
            background-color: var(--secondary-color);
        }

        .footer {
            background-color: var(--primary-color);
            color: white;
            padding: 15px;
            text-align: center;
            margin-top: auto;
        }

    </style>
</head>
<body>
    <header class="header">
        <div class="header-left">
            <button class="togglebutton" id="togglebutton">
                <i class="fas fa-bars"></i> Menu 
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
                <a href="EventRegistration.php" class="menuitem active">
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
            <h1>Event Registration Status</h1>
            <?php
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                $staffID = $_SESSION['user_id'] ?? null;
                if (!$staffID) {
                    die("<div class='message error'>Error: No staff ID found. Please log in.</div>");
                }

                $link = mysqli_connect("localhost", "root", "", "mypetakom");
                if (mysqli_connect_errno()) {
                    die("<div class='message error'>Database connection failed: " . mysqli_connect_error() . "</div>");
                }

                // Validate staff
                $checkStaff = mysqli_query($link, "SELECT StaffID FROM staff WHERE StaffID = '$staffID'");
                if (mysqli_num_rows($checkStaff) == 0) {
                    die("<div class='message error'>Error: Invalid staff ID. You don't have permission to create events.</div>");
                }

                // Get form data
                $eventTitle = $_POST["title"] ?? '';
                $eventVenue = $_POST["venue"] ?? '';
                $eventLevel = $_POST["eventlevel"] ?? ''; // Get Event Level
                $eventDateTime = $_POST["dateandtime"] ?? '';
                $eventStatus = "Pending";
                $approvalLetter = null;
                $geolocation = $_POST["geolocation"] ?? '';

                // Handle file upload
                if (isset($_FILES['approval']) && $_FILES['approval']['error'] == UPLOAD_ERR_OK) {
                    $uploadDir = "uploads/";
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $filename = basename($_FILES['approval']['name']);
                    $targetPath = $uploadDir . time() . "_" . $filename;
                    if (move_uploaded_file($_FILES['approval']['tmp_name'], $targetPath)) {
                        $approvalLetter = $targetPath;
                    } else {
                        die("<div class='message error'>Error uploading approval letter.</div>");
                    }
                }

                // Generate Event ID
                $eventID = "EV" . uniqid();
                $qrCodeID = null;

                $query = "INSERT INTO event (EventID, StaffID, QRCodeID, EventTitle, EventDateandTime, EventVenue, EventStatus, ApprovalLetter, geolocation, EventLevel)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = mysqli_prepare($link, $query);

                if (!$stmt) {
                    die("<div class='message error'>Prepare failed: " . mysqli_error($link) . "</div>");
                }

                mysqli_stmt_bind_param(
                    $stmt, "ssssssssss",
                    $eventID,
                    $staffID,
                    $qrCodeID,
                    $eventTitle,
                    $eventDateTime,
                    $eventVenue,
                    $eventStatus,
                    $approvalLetter,
                    $geolocation,
                    $eventLevel
                );

                if (mysqli_stmt_execute($stmt)) {
                    echo "<div class='message success'>Event registered successfully!<br><br>";
                    echo "<strong>Event ID:</strong> $eventID<br>";
                    echo "<strong>Status:</strong> $eventStatus<br>";
                    echo "</div>";
                } else {
                    echo "<div class='message error'>Registration failed: " . mysqli_stmt_error($stmt) . "</div>";
                }

                mysqli_stmt_close($stmt);
                mysqli_close($link);
            } else {
                echo "<div class='message error'>Invalid request method. Please submit the form.</div>";
            }
            ?>
            <a href="EventRegistration.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Registration Form
            </a>
        </div>
    </div>
    <div class="footer">
        <footer>
            <p>&copy; 2025 MyPetakom</p>
        </footer>
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

            // Responsive behavior
            function handleResize() {
                if (window.innerWidth <= 768) {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('expanded');
                } else {
                    sidebar.classList.remove('collapsed');
                    mainContent.classList.remove('expanded');
                }
            }

            window.addEventListener('resize', handleResize);
            handleResize(); // Initial check
        });
    </script>
</body>
</html>