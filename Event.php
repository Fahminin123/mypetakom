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

// Handle delete action
if (isset($_GET['delete'])) {
    $eventID = $_GET['delete'];
    
    // Start transaction for atomic operations
    $conn->begin_transaction();
    
    try {
        // 1. Get event and QR code details, and ApprovalLetter
        $getDetailsQuery = "SELECT e.QRCodeID, q.Image_URL, e.ApprovalLetter
                           FROM event e 
                           LEFT JOIN qrcode q ON e.QRCodeID = q.QRCodeID 
                           WHERE e.EventID = ? AND e.StaffID = ?";
        $stmt = $conn->prepare($getDetailsQuery);
        $stmt->bind_param("ss", $eventID, $staff_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Event not found or you don't have permission to delete it.");
        }
        
        $eventData = $result->fetch_assoc();
        $qrCodeID = $eventData['QRCodeID'];
        $qrImagePath = $eventData['Image_URL'];
        $approvalLetter = $eventData['ApprovalLetter'];
        
        // 2. Delete the event
        $deleteEventQuery = "DELETE FROM event WHERE EventID = ? AND StaffID = ?";
        $stmt = $conn->prepare($deleteEventQuery);
        $stmt->bind_param("ss", $eventID, $staff_id);
        $stmt->execute();
        
        // 3. If exists, delete the QR code record and file
        if ($qrCodeID && $qrImagePath) {
            // Delete QR code record
            $deleteQRQuery = "DELETE FROM qrcode WHERE QRCodeID = ?";
            $stmt = $conn->prepare($deleteQRQuery);
            $stmt->bind_param("s", $qrCodeID);
            $stmt->execute();
            
            // Delete physical QR file with safety checks
            $baseQRDir = realpath('uploads/qrcodes/') . DIRECTORY_SEPARATOR;
            $fullQRPath = realpath($qrImagePath);
            
            if ($fullQRPath && strpos($fullQRPath, $baseQRDir) === 0 && is_file($fullQRPath)) {
                if (!unlink($fullQRPath)) {
                    throw new Exception("Failed to delete QR code file.");
                }
            }
        }

        // 4. If exists, delete the Approval Letter file
        if ($approvalLetter) {
            // Only keep the filename (in case DB has path)
            $approvalLetterFile = basename($approvalLetter);
            $baseLetterDir = realpath('uploads/') . DIRECTORY_SEPARATOR;
            $approvalLetterPath = realpath('uploads/' . $approvalLetterFile);

            if ($approvalLetterPath && strpos($approvalLetterPath, $baseLetterDir) === 0 && is_file($approvalLetterPath)) {
                if (!unlink($approvalLetterPath)) {
                    throw new Exception("Failed to delete Approval Letter file.");
                }
            }
        }
        
        // Commit if all operations succeeded
        $conn->commit();
        $successMessage = "Event deleted successfully!";
        
    } catch (Exception $e) {
        $conn->rollback();
        $errorMessage = "Error: " . $e->getMessage();
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

// Fetch all events for this advisor
$eventsQuery = "SELECT * FROM event WHERE StaffID = ?";
$stmt = $conn->prepare($eventsQuery);
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$eventsResult = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Event</title>
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

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        tr:hover {
            background-color: #f5f5f5;
        }

        .actionbutton {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            transition: background-color 0.2s;
            margin-right: 5px;
            text-decoration: none;
            display: inline-block;
        }

        .update {
            background-color: #ffc107;
            color: #212529;
        }
        
        .delete {
            background-color: #dc3545;
            color: white;
        }
        
        .view {
            background-color: #0d6efd;
            color: white;
        }

        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-weight: bold;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
            <h1>Event</h1>
            <?php if (isset($successMessage)): ?>
                <div class="message success"><?php echo $successMessage; ?></div>
            <?php endif; ?>
            <?php if (isset($errorMessage)): ?>
                <div class="message error"><?php echo $errorMessage; ?></div>
            <?php endif; ?>
        </div>

        <div class="seccontent">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Venue</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($eventsResult->num_rows > 0): ?>
                        <?php while ($event = $eventsResult->fetch_assoc()): ?>
                            <tr class="event-row">
                                <td><?php echo htmlspecialchars($event['EventTitle']); ?></td>
                                <td><?php echo htmlspecialchars($event['EventVenue']); ?></td>
                                <td><?php echo date('j F Y, g:i A', strtotime($event['EventDateandTime'])); ?></td>
                                <td>
                                    <span>
                                        <?php echo $event['EventStatus']; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="UpdateEvent.php?id=<?php echo $event['EventID']; ?>" class="actionbutton update">
                                        <i class="fas fa-edit"></i> Update
                                    </a>
                                    <a href="Event.php?delete=<?php echo $event['EventID']; ?>" 
                                       class="actionbutton delete" 
                                       onclick="return confirm('Are you sure you want to delete this event?');">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                    <a href="ViewEvent.php?id=<?php echo $event['EventID']; ?>" class="actionbutton view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">No events found</td>
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